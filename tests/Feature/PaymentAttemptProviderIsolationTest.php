<?php

/**
 * F-1 — Provider-aware pending-attempt reuse.
 *
 * A stale PENDING attempt from provider A must NOT be reused by provider B.
 * The "one active pending attempt per bill (per provider)" guard is kept intact.
 */

use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Support\Facades\Http;

function f1Student(): Student {
    return Student::factory()->create();
}
function f1Bill(Student $s): StudentBill {
    return StudentBill::factory()->create([
        'student_id' => $s->id,
        'status'     => 'UNPAID',
        'amount'     => 600000,
    ]);
}

// ── 1. Midtrans IGNORES a pending FLIP attempt ───────────────────────────────
test('midtrans does not reuse a pending FLIP attempt', function () {
    $s    = f1Student();
    $bill = f1Bill($s);

    // Stale FLIP pending attempt (no snap_token — provider session via URL).
    PaymentAttempt::factory()->flip($bill)->create([
        'status'    => PaymentAttempt::STATUS_PENDING,
        'snap_token' => null,
    ]);

    $this->actingAs($s, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay");

    // The stale FLIP attempt must remain the only FLIP attempt; the Midtrans
    // lookup must not return it. Bill must NOT point at the FLIP order.
    $bill->refresh();
    expect($bill->midtrans_order_id)->not->toStartWith('FLIP-');
    expect(PaymentAttempt::where('student_bill_id', $bill->id)
        ->where('provider', 'FLIP')->count())->toBe(1);
});

// ── 2. Midtrans REUSES a pending MIDTRANS attempt ────────────────────────────
test('midtrans reuses its own pending attempt (no duplicate)', function () {
    $s    = f1Student();
    $bill = f1Bill($s);

    PaymentAttempt::factory()->create([
        'student_bill_id' => $bill->id,
        'provider'        => 'MIDTRANS',
        'status'          => PaymentAttempt::STATUS_PENDING,
        'snap_token'      => 'existing-midtrans-token',
    ]);

    $resp = $this->actingAs($s, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay");

    $resp->assertOk();
    // Reused the existing Midtrans attempt's token (no new Snap call needed).
    expect($resp->json('snap_token'))->toBe('existing-midtrans-token');
    expect(PaymentAttempt::where('student_bill_id', $bill->id)
        ->where('provider', 'MIDTRANS')
        ->where('status', 'pending')->count())->toBe(1);
});

// ── 3. Flip IGNORES a pending MIDTRANS attempt ───────────────────────────────
test('flip does not reuse a pending MIDTRANS attempt', function () {
    Http::fake([
        '*' => Http::response(['link_id' => 123456789, 'link_url' => 'https://flip.test/bill/abc'], 200),
    ]);
    config(['services.flip.secret_key' => 'test-flip-secret', 'services.flip.api_url' => 'https://flip.test']);

    $s    = f1Student();
    $bill = f1Bill($s);

    // Pending MIDTRANS attempt only.
    PaymentAttempt::factory()->create([
        'student_bill_id' => $bill->id,
        'provider'        => 'MIDTRANS',
        'status'          => PaymentAttempt::STATUS_PENDING,
    ]);

    $this->actingAs($s, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay-flip");

    // A NEW FLIP attempt must be created (the Midtrans one must not be reused).
    expect(PaymentAttempt::where('student_bill_id', $bill->id)->where('provider', 'FLIP')->count())->toBe(1);
    // The original Midtrans attempt remains untouched.
    expect(PaymentAttempt::where('student_bill_id', $bill->id)->where('provider', 'MIDTRANS')->count())->toBe(1);
});

// ── 4. Flip REUSES a pending FLIP attempt ────────────────────────────────────
test('flip reuses its own pending attempt (no duplicate)', function () {
    $s    = f1Student();
    $bill = f1Bill($s);

    PaymentAttempt::factory()->flip($bill, '777888999')->create([
        'status'          => PaymentAttempt::STATUS_PENDING,
        'provider_status' => 'PENDING',
    ]);

    Http::fake(['*' => Http::response([], 500)]); // must NOT be called for a reuse

    $this->actingAs($s, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay-flip");

    expect(PaymentAttempt::where('student_bill_id', $bill->id)->where('provider', 'FLIP')->count())->toBe(1);
    Http::assertNothingSent(); // reused → no new provider call
});

// ── 5. Concurrent same-provider requests remain single-attempt ───────────────
test('concurrent midtrans requests reuse the same attempt', function () {
    $s    = f1Student();
    $bill = f1Bill($s);

    PaymentAttempt::factory()->create([
        'student_bill_id' => $bill->id,
        'provider'        => 'MIDTRANS',
        'status'          => PaymentAttempt::STATUS_PENDING,
        'snap_token'      => 'shared-token',
    ]);

    $this->actingAs($s, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay");
    $this->actingAs($s, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay");

    expect(PaymentAttempt::where('student_bill_id', $bill->id)
        ->where('provider', 'MIDTRANS')->where('status', 'pending')->count())->toBe(1);
});

// ── 6. Terminal attempts are never reused ────────────────────────────────────
test('a terminal (settlement) midtrans attempt is not reused', function () {
    $s    = f1Student();
    $bill = f1Bill($s);

    PaymentAttempt::factory()->create([
        'student_bill_id' => $bill->id,
        'provider'        => 'MIDTRANS',
        'status'          => PaymentAttempt::STATUS_SETTLEMENT,
    ]);

    // No PENDING midtrans attempt exists, so createToken proceeds to the Snap
    // branch (network will fail in test → HTTP 500), proving it did NOT reuse
    // the terminal attempt as "active".
    $resp = $this->actingAs($s, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay");
    expect($resp->status())->not->toBe(200); // did not return the settled attempt
    expect(PaymentAttempt::where('student_bill_id', $bill->id)
        ->where('provider', 'MIDTRANS')->where('status', 'settlement')->count())->toBe(1);
});
