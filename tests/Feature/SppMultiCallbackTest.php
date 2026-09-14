<?php

/**
 * Phase 09B.2 — SPP source hardening.
 *
 * Closes a real amount-integrity gap and proves replay/idempotency behaviour
 * for the MULTI-bill Midtrans callback path (which previously had NO
 * gross_amount validation, unlike the single-bill path).
 *
 * Scenarios:
 *   M1. valid MULTI settlement (correct total) marks ALL bills PAID
 *   M2. MULTI amount mismatch (underpay) → NO bill is settled
 *   M3. MULTI amount mismatch (overpay) → NO bill is settled
 *   M4. MULTI amount zero → rejected, no mutation
 *   M5. repeated identical MULTI settlement is idempotent (one audit per bill,
 *       paid_at stable, no double effect)
 *   M6. MULTI callback with invalid signature → no mutation
 *   M7. MULTI callback for nonexistent order/bills → 200, no mutation
 */

use App\Models\AuditLog;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Support\Facades\Config;

/** Build a signed Midtrans webhook payload (MULTI or single). */
function sppWebhook(string $orderId, float $amount, string $status, string $serverKey = 'test-server-key-xyz'): array
{
    $statusCode  = $status === 'expire' ? '407' : '200';
    $grossAmount = number_format($amount, 2, '.', '');
    $signature   = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

    return [
        'order_id'           => $orderId,
        'status_code'        => $statusCode,
        'gross_amount'       => $grossAmount,
        'transaction_status' => $status,
        'fraud_status'       => 'accept',
        'signature_key'      => $signature,
    ];
}

/** Build a MULTI- order id for the given bill ids. */
function multiOrderId(array $billIds): string
{
    return 'MULTI-' . implode('_', $billIds) . '-abc123-' . time();
}

beforeEach(function () {
    Config::set('services.midtrans.server_key', 'test-server-key-xyz');
});

// ── M1. valid MULTI settlement marks all bills PAID ─────────────────────────
test('M1: valid MULTI settlement marks all bills PAID', function () {
    $a = StudentBill::factory()->create(['amount' => 100000, 'status' => 'UNPAID']);
    $b = StudentBill::factory()->create(['amount' => 250000, 'status' => 'UNPAID']);
    $total  = 100000 + 250000;
    $orderId = multiOrderId([$a->id, $b->id]);

    $this->postJson('/siswa/payment/callback', sppWebhook($orderId, $total, 'settlement'))
         ->assertOk();

    foreach ([$a, $b] as $bill) {
        $bill->refresh();
        expect($bill->status)->toBe('PAID');
        expect($bill->paid_at)->not->toBeNull();
        expect($bill->payment_method)->toBe('MIDTRANS');
    }
});

// ── M2. underpayment → no bill settled ──────────────────────────────────────
test('M2: MULTI amount mismatch (underpay) settles no bill', function () {
    $a = StudentBill::factory()->create(['amount' => 100000, 'status' => 'UNPAID']);
    $b = StudentBill::factory()->create(['amount' => 250000, 'status' => 'UNPAID']);
    $orderId = multiOrderId([$a->id, $b->id]);

    // Provider reports LESS than the true total.
    $this->postJson('/siswa/payment/callback', sppWebhook($orderId, 300000, 'settlement'))
         ->assertOk();

    expect($a->fresh()->status)->toBe('UNPAID');
    expect($b->fresh()->status)->toBe('UNPAID');
});

// ── M3. overpayment → no bill settled ───────────────────────────────────────
test('M3: MULTI amount mismatch (overpay) settles no bill', function () {
    $a = StudentBill::factory()->create(['amount' => 100000, 'status' => 'UNPAID']);
    $b = StudentBill::factory()->create(['amount' => 250000, 'status' => 'UNPAID']);
    $orderId = multiOrderId([$a->id, $b->id]);

    $this->postJson('/siswa/payment/callback', sppWebhook($orderId, 500000, 'settlement'))
         ->assertOk();

    expect($a->fresh()->status)->toBe('UNPAID');
    expect($b->fresh()->status)->toBe('UNPAID');
});

// ── M4. zero amount → rejected ──────────────────────────────────────────────
test('M4: MULTI callback with zero amount settles no bill', function () {
    $a = StudentBill::factory()->create(['amount' => 100000, 'status' => 'UNPAID']);
    $b = StudentBill::factory()->create(['amount' => 250000, 'status' => 'UNPAID']);
    $orderId = multiOrderId([$a->id, $b->id]);

    $this->postJson('/siswa/payment/callback', sppWebhook($orderId, 0, 'settlement'))
         ->assertOk();

    expect($a->fresh()->status)->toBe('UNPAID');
    expect($b->fresh()->status)->toBe('UNPAID');
});

// ── M5. repeated MULTI settlement is idempotent ──────────────────────────────
test('M5: repeated MULTI settlement is idempotent', function () {
    $a = StudentBill::factory()->create(['amount' => 100000, 'status' => 'UNPAID']);
    $b = StudentBill::factory()->create(['amount' => 250000, 'status' => 'UNPAID']);
    $total   = 350000;
    $orderId = multiOrderId([$a->id, $b->id]);
    $payload = sppWebhook($orderId, $total, 'settlement');

    // First callback settles.
    $this->postJson('/siswa/payment/callback', $payload)->assertOk();
    $paidAtA = $a->fresh()->paid_at;
    $paidAtB = $b->fresh()->paid_at;
    $auditCountAfterFirst = AuditLog::where('action', AuditLog::PAYMENT_CONFIRMED)->count();

    // Replay the same callback many times.
    $this->postJson('/siswa/payment/callback', $payload)->assertOk();
    $this->postJson('/siswa/payment/callback', $payload)->assertOk();
    $this->postJson('/siswa/payment/callback', $payload)->assertOk();

    // paid_at unchanged, payment_method unchanged, no extra PAYMENT_CONFIRMED.
    expect($a->fresh()->paid_at->toDateTimeString())->toBe($paidAtA->toDateTimeString());
    expect($b->fresh()->paid_at->toDateTimeString())->toBe($paidAtB->toDateTimeString());
    expect(AuditLog::where('action', AuditLog::PAYMENT_CONFIRMED)->count())
        ->toBe($auditCountAfterFirst);
});

// ── M6. invalid signature → no mutation ──────────────────────────────────────
test('M6: MULTI callback with invalid signature mutates nothing', function () {
    $a = StudentBill::factory()->create(['amount' => 100000, 'status' => 'UNPAID']);
    $b = StudentBill::factory()->create(['amount' => 250000, 'status' => 'UNPAID']);
    $orderId = multiOrderId([$a->id, $b->id]);

    $payload = sppWebhook($orderId, 350000, 'settlement');
    $payload['signature_key'] = 'forged-signature';

    $this->postJson('/siswa/payment/callback', $payload)->assertOk();

    expect($a->fresh()->status)->toBe('UNPAID');
    expect($b->fresh()->status)->toBe('UNPAID');
});

// ── M7. nonexistent order/bills → 200, no mutation ───────────────────────────
test('M7: MULTI callback for nonexistent bills mutates nothing', function () {
    $orderId = 'MULTI-999991_999992-xyz-' . time();

    $this->postJson('/siswa/payment/callback', sppWebhook($orderId, 350000, 'settlement'))
         ->assertOk();

    // No bill is created or modified; no audit written.
    expect(AuditLog::where('action', AuditLog::PAYMENT_CONFIRMED)->count())->toBe(0);
});
