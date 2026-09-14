<?php

use App\Models\Kelas;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 09B.1 — Stable student external identity (student_uuid).
 *
 * Proves the identity lifecycle contract:
 *   1. generated on creation
 *   2. unique
 *   3. immutable across name / NIS / class changes
 *   4. not regenerated on re-save
 *   5. survives soft delete and restore
 *   6. protected from mass-assignment / request input
 *   7. duplicate rejected at the database level
 */

// ── 1. Generated on creation ─────────────────────────────────────────────────
test('a new student gets a student_uuid on creation', function () {
    $student = Student::factory()->create();

    expect($student->student_uuid)->not->toBeNull()
        ->and(Str::isUuid($student->student_uuid))->toBeTrue();
});

// ── 2. Unique ────────────────────────────────────────────────────────────────
test('student_uuid is unique across many students', function () {
    $students = Student::factory()->count(50)->create();
    $uuids    = $students->pluck('student_uuid');

    expect($uuids->unique()->count())->toBe(50);
});

// ── 3. Survives name change ──────────────────────────────────────────────────
test('updating student name does not change student_uuid', function () {
    $student = Student::factory()->create(['name' => 'Old Name']);
    $uuid    = $student->student_uuid;

    $student->update(['name' => 'Completely New Name']);

    expect($student->fresh()->student_uuid)->toBe($uuid);
});

// ── 4. Survives NIS change ───────────────────────────────────────────────────
test('updating NIS does not change student_uuid', function () {
    $student = Student::factory()->create();
    $uuid    = $student->student_uuid;

    $student->update(['nis' => '999888777']);

    expect($student->fresh()->student_uuid)->toBe($uuid);
});

// ── 5. Survives class change ─────────────────────────────────────────────────
test('changing class does not change student_uuid', function () {
    $kelasA  = Kelas::create(['nama_kelas' => 'X IPA 1', 'tingkat' => 10, 'is_aktif' => true]);
    $kelasB  = Kelas::create(['nama_kelas' => 'XI IPA 1', 'tingkat' => 11, 'is_aktif' => true]);
    $student = Student::factory()->create(['kelas_id' => $kelasA->id]);
    $uuid    = $student->student_uuid;

    $student->update(['kelas_id' => $kelasB->id]);

    expect($student->fresh()->student_uuid)->toBe($uuid);
});

// ── 6. Re-saving does not regenerate ─────────────────────────────────────────
test('re-saving the student does not regenerate student_uuid', function () {
    $student = Student::factory()->create();
    $uuid    = $student->student_uuid;

    $student->touch();
    $student->save();
    $student->update(['address' => 'New address']);

    expect($student->fresh()->student_uuid)->toBe($uuid);
});

// ── 7. Soft delete does not change the ID ────────────────────────────────────
test('soft deletion does not change student_uuid', function () {
    $student = Student::factory()->calon()->create(); // calon_siswa: deletable
    $uuid    = $student->student_uuid;

    $student->delete();

    $fresh = Student::withTrashed()->find($student->id);
    expect($fresh->student_uuid)->toBe($uuid)
        ->and($fresh->trashed())->toBeTrue();
});

// ── 8. Restore does not change the ID ────────────────────────────────────────
test('restoring a soft-deleted student does not change student_uuid', function () {
    $student = Student::factory()->calon()->create();
    $uuid    = $student->student_uuid;

    $student->delete();
    $student->restore();

    expect($student->fresh()->student_uuid)->toBe($uuid)
        ->and($student->fresh()->trashed())->toBeFalse();
});

// ── 9. Not mass-assignable ───────────────────────────────────────────────────
test('student_uuid cannot be set via mass assignment on create', function () {
    $attackerUuid = (string) Str::uuid();

    $student = Student::create([
        'nis'          => '111222333',
        'name'         => 'Mass Assignment Target',
        'gender'       => 'L',
        'status'       => 'active',
        'student_uuid' => $attackerUuid, // must be ignored
    ]);

    expect($student->student_uuid)->not->toBe($attackerUuid)
        ->and(Str::isUuid($student->student_uuid))->toBeTrue();
});

test('student_uuid cannot be changed via mass assignment on update', function () {
    $student = Student::factory()->create();
    $uuid    = $student->student_uuid;

    $student->update(['student_uuid' => (string) Str::uuid()]);

    expect($student->fresh()->student_uuid)->toBe($uuid);
});

// ── 10. Duplicate rejected by database ───────────────────────────────────────
test('duplicate student_uuid is rejected by the database', function () {
    $a = Student::factory()->create();
    $b = Student::factory()->create();

    // Force a collision at the query level (bypasses the creating hook).
    DB::table('students')->where('id', $b->id)->update(['student_uuid' => $a->student_uuid]);
})->throws(\Illuminate\Database\QueryException::class);
