<?php

/**
 * Riwayat Pembayaran (Lunas) — Filter + Pagination Tests
 *
 * Covers DashboardSiswaController::history():
 *   1. Page renders for authenticated student (200).
 *   2. Filter by tahun only shows that period's paid bills.
 *   3. Filter by bulan only shows that month across years.
 *   4. Filter by tahun + bulan shows the exact period.
 *   5. Invalid filter params are ignored (show all).
 *   6. Pagination kicks in at > 10 records and preserves filters.
 *   7. Unauthenticated request redirects to siswa login.
 */

use App\Models\Student;
use App\Models\StudentBill;

function makePaidBill(Student $student, int $year, int $month): StudentBill
{
    return StudentBill::factory()->paid()->create([
        'student_id' => $student->id,
        'bill_year'  => $year,
        'bill_month' => $month,
        'name'       => "SPP {$month}/{$year}",
    ]);
}

// ── 1. Renders ───────────────────────────────────────────────────────────────
test('history page renders for authenticated student', function () {
    $student = Student::factory()->create();
    makePaidBill($student, 2025, 1);

    $this->actingAs($student, 'siswa')
         ->get(route('siswa.history'))
         ->assertStatus(200)
         ->assertSee('Riwayat Pembayaran');
});

// ── 2. Filter by tahun ───────────────────────────────────────────────────────
test('filter by tahun only shows that year paid bills', function () {
    $student = Student::factory()->create();
    makePaidBill($student, 2024, 6);
    makePaidBill($student, 2025, 6);

    $res = $this->actingAs($student, 'siswa')
                ->get(route('siswa.history', ['tahun' => 2025]));

    $res->assertStatus(200);
    $res->assertSee('SPP 6/2025');
    $res->assertDontSee('SPP 6/2024');
});

// ── 3. Filter by bulan across years ──────────────────────────────────────────
test('filter by bulan only shows that month across years', function () {
    $student = Student::factory()->create();
    makePaidBill($student, 2024, 3);
    makePaidBill($student, 2025, 3);
    makePaidBill($student, 2025, 4);

    $res = $this->actingAs($student, 'siswa')
                ->get(route('siswa.history', ['bulan' => 3]));

    $res->assertSee('SPP 3/2024');
    $res->assertSee('SPP 3/2025');
    $res->assertDontSee('SPP 4/2025');
});

// ── 4. Filter by tahun + bulan (exact period) ────────────────────────────────
test('filter by tahun and bulan shows exact period only', function () {
    $student = Student::factory()->create();
    makePaidBill($student, 2025, 3);
    makePaidBill($student, 2025, 4);
    makePaidBill($student, 2024, 3);

    $res = $this->actingAs($student, 'siswa')
                ->get(route('siswa.history', ['tahun' => 2025, 'bulan' => 3]));

    $res->assertSee('SPP 3/2025');
    $res->assertDontSee('SPP 4/2025');
    $res->assertDontSee('SPP 3/2024');
});

// ── 5. Invalid filters ignored ───────────────────────────────────────────────
test('invalid filter params are ignored and show all paid bills', function () {
    $student = Student::factory()->create();
    makePaidBill($student, 2024, 6);
    makePaidBill($student, 2025, 6);

    $res = $this->actingAs($student, 'siswa')
                ->get(route('siswa.history', ['tahun' => 'abc', 'bulan' => 99]));

    $res->assertSee('SPP 6/2024');
    $res->assertSee('SPP 6/2025');
});

// ── 6. Pagination + filter preservation ──────────────────────────────────────
test('pagination activates over 10 records and preserves filters', function () {
    $student = Student::factory()->create();
    foreach (range(1, 12) as $m) {
        makePaidBill($student, 2025, $m);
    }

    // Page 1: shows pagination control and preserves the tahun filter in links.
    $page1 = $this->actingAs($student, 'siswa')
                  ->get(route('siswa.history', ['tahun' => 2025]));
    $page1->assertStatus(200);
    $page1->assertSee('Berikutnya');
    $page1->assertSee('tahun=2025', false); // filter preserved in next-page URL

    // Page 2 via filter-preserving URL.
    $page2 = $this->actingAs($student, 'siswa')
                  ->get(route('siswa.history', ['tahun' => 2025, 'page' => 2]));
    $page2->assertStatus(200);
});

// ── 7. Auth guard ────────────────────────────────────────────────────────────
test('unauthenticated request to history redirects to siswa login', function () {
    $this->get(route('siswa.history'))
         ->assertRedirect(route('siswa.login'));
});
