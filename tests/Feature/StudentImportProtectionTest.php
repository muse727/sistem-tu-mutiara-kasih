<?php

use App\Models\Kelas;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Phase 09B.1 — Import duplicate protection.
 *
 * The CSV importer matches students by the authoritative `nis` column only.
 * It never matches by name. Proves:
 *   A. repeated identical import does not create duplicates
 *   B. duplicate NIS is rejected (flagged for review, not silently merged)
 *   C. duplicate NISN is rejected
 *   D. two existing students with the same name are NOT confused/guessed
 *   E. missing required identity (nis) is rejected deterministically
 *   F. a soft-deleted student's NIS cannot be hijacked/reused by an import
 *   G. student_uuid in the payload is ignored (no identity takeover)
 *   H. every imported student receives a distinct student_uuid
 */

function makeKelasForImport(string $nama = 'X - Umum'): Kelas
{
    return Kelas::create([
        'nama_kelas' => $nama,
        'tingkat'    => 10,
        'is_aktif'   => true,
    ]);
}

/** Build a CSV upload with the given data rows (header included). */
function importCsvPayload(array $rows): UploadedFile
{
    $header  = ['nis', 'nisn', 'name', 'gender', 'nama_kelas', 'birth_date', 'parent_phone'];
    $content = implode(',', $header) . "\n";
    foreach ($rows as $r) {
        $content .= implode(',', array_map(fn ($c) => (string) $c, $r)) . "\n";
    }
    return UploadedFile::fake()->createWithContent('students.csv', $content);
}

// ── A & B. repeated import / duplicate NIS does not create duplicates ────────
test('importing the same NIS twice does not create duplicate students', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    makeKelasForImport();

    $row = ['2024001', '', 'Ahmad Siswa', 'L', 'X - Umum', '2008-05-10', '08123456789'];

    // First import → creates the student.
    $this->actingAs($admin)->post('/students/import', ['file' => importCsvPayload([$row])]);
    expect(Student::where('nis', '2024001')->count())->toBe(1);

    // Second import of the identical row → rejected, still only one student.
    $this->actingAs($admin)->post('/students/import', ['file' => importCsvPayload([$row])]);
    expect(Student::where('nis', '2024001')->count())->toBe(1);
});

// ── D. two students with the same name are not guessed/merged ────────────────
test('import does not match an existing student by name alone', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    makeKelasForImport();

    // Existing student with a given name + NIS.
    $existing = Student::factory()->create(['name' => 'Budi Santoso', 'nis' => '100001']);

    // A genuinely NEW student happens to share the same name but a different NIS.
    $newRow = ['100002', '', 'Budi Santoso', 'L', 'X - Umum', '2009-01-01', '08111111111'];
    $this->actingAs($admin)->post('/students/import', ['file' => importCsvPayload([$newRow])]);

    // Both students must exist independently — no merge, no overwrite.
    expect(Student::where('name', 'Budi Santoso')->count())->toBe(2);
    expect(Student::where('nis', '100001')->first()->id)->toBe($existing->id);
    expect(Student::where('nis', '100002')->count())->toBe(1);
});

// ── C. duplicate NISN is rejected ────────────────────────────────────────────
test('import rejects a row whose NISN already exists', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    makeKelasForImport();

    Student::factory()->create(['nis' => '200001', 'nisn' => '9988776655']);

    $row = ['200002', '9988776655', 'New Student', 'P', 'X - Umum', '2009-01-01', '08122222222'];
    $this->actingAs($admin)->post('/students/import', ['file' => importCsvPayload([$row])]);

    expect(Student::where('nis', '200002')->count())->toBe(0);
    expect(Student::where('nisn', '9988776655')->count())->toBe(1);
});

// ── E. missing required identity (nis) is rejected deterministically ─────────
test('import rejects a row with a missing NIS', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    makeKelasForImport();

    $row = ['', '', 'No Nis Student', 'L', 'X - Umum', '2009-01-01', '08133333333'];
    $this->actingAs($admin)->post('/students/import', ['file' => importCsvPayload([$row])]);

    expect(Student::where('name', 'No Nis Student')->count())->toBe(0);
});

// ── F. soft-deleted student's NIS cannot be reused via import ────────────────
test('import does not reuse the NIS of a soft-deleted student', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    makeKelasForImport();

    $deleted = Student::factory()->calon()->create(['nis' => '300001']);
    $deleted->delete();

    $row = ['300001', '', 'Replacement Student', 'L', 'X - Umum', '2009-01-01', '08144444444'];
    $this->actingAs($admin)->post('/students/import', ['file' => importCsvPayload([$row])]);

    // The soft-deleted NIS must NOT be hijacked: no NEW active student created.
    expect(Student::where('nis', '300001')->count())->toBe(0);
    // And the soft-deleted row still holds that NIS.
    expect(Student::withTrashed()->where('nis', '300001')->count())->toBe(1);
});

// ── G. student_uuid in payload is ignored (no identity takeover) ─────────────
test('import ignores any student_uuid in the row and assigns its own', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    makeKelasForImport();

    $victim = Student::factory()->create();

    // Build a CSV that injects a student_uuid column pointing at the victim.
    $header  = 'nis,nisn,name,gender,nama_kelas,student_uuid';
    $line    = "400001,,Identity Thief,L,X - Umum,{$victim->student_uuid}";
    $file    = UploadedFile::fake()->createWithContent('students.csv', $header . "\n" . $line . "\n");

    $this->actingAs($admin)->post('/students/import', ['file' => $file]);

    $created = Student::where('nis', '400001')->first();
    expect($created)->not->toBeNull();
    expect($created->student_uuid)->not->toBe($victim->student_uuid);
    // Victim's identity unchanged.
    expect($victim->fresh()->student_uuid)->not->toBeNull();
});

// ── H. each imported student receives a distinct student_uuid ────────────────
test('every imported student receives a distinct student_uuid', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    makeKelasForImport();

    $rows = [
        ['500001', '', 'Student One', 'L', 'X - Umum', '2009-01-01', '08100000001'],
        ['500002', '', 'Student Two', 'P', 'X - Umum', '2009-02-02', '08100000002'],
        ['500003', '', 'Student Three', 'L', 'X - Umum', '2009-03-03', '08100000003'],
    ];
    $this->actingAs($admin)->post('/students/import', ['file' => importCsvPayload($rows)]);

    $uuids = Student::whereIn('nis', ['500001', '500002', '500003'])->pluck('student_uuid');
    expect($uuids)->toHaveCount(3);
    expect($uuids->unique())->toHaveCount(3);
    $uuids->each(fn ($u) => expect(Str::isUuid($u))->toBeTrue());
});
