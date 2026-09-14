<?php

/**
 * Phase 09B.3 — POS source hardening.
 *
 * Proves the POS (canteen debt) payment lifecycle is consistent and idempotent.
 *
 * Covers the previously-broken `handlePosCallback` (it wrote to non-existent
 * `paid_amount` / `payment_method` columns, which would have thrown and rolled
 * back every POS settlement) and the surrounding replay/integrity guarantees.
 *
 * Scenarios:
 *   P1. valid POS settlement marks order PAID + settles linked KANTIN bill
 *   P2. repeated identical POS settlement is idempotent (no double effect)
 *   P3. POS amount mismatch → order NOT settled, KANTIN bill unchanged
 *   P4. POS callback with invalid signature → nothing mutates
 *   P5. POS callback for nonexistent order → 200, nothing mutates
 *   P6. already-PAID (cash) order receiving a provider callback is a no-op
 *   P7. settle uses only real columns (payment_status/payment_amount/change)
 */

use App\Models\AuditLog;
use App\Models\PosOrder;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Support\Facades\Config;

/** Build a signed Midtrans POS webhook payload. */
function posWebhook(string $orderId, float $amount, string $status, string $serverKey = 'test-server-key-xyz'): array
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

/** Create an UNPAID POS order (canteen debt) with a linked KANTIN bill. */
function posDebtOrder(int $amount = 15000): array
{
    $student = Student::factory()->create();
    $order   = PosOrder::create([
        'user_id'           => null,
        'student_id'        => $student->id,
        'transaction_code'  => 'TRX-' . time() . strtoupper(\Illuminate\Support\Str::random(3)),
        'total_amount'      => $amount,
        'payment_amount'    => 0,
        'change_amount'     => 0,
        'payment_status'    => 'UNPAID',
        'redemption_status' => 'PENDING',
    ]);
    $bill = StudentBill::create([
        'pos_order_id' => $order->id,
        'student_id'   => $student->id,
        'name'         => 'Kantin/Koperasi ' . now()->format('d/m/Y'),
        'type'         => 'KANTIN',
        'amount'       => $amount,
        'status'       => 'UNPAID',
    ]);

    return [$order, $bill];
}

beforeEach(function () {
    Config::set('services.midtrans.server_key', 'test-server-key-xyz');
});

// ── P1. valid POS settlement settles order + KANTIN bill ────────────────────
test('P1: valid POS settlement marks order PAID and settles KANTIN bill', function () {
    [$order, $bill] = posDebtOrder(15000);
    $orderId = "POS-{$order->id}-abc123-" . time();

    $this->postJson('/api/midtrans-callback', posWebhook($orderId, 15000, 'settlement'))
         ->assertOk();

    $order->refresh();
    $bill->refresh();

    expect($order->payment_status)->toBe('PAID');
    expect($order->payment_amount)->toBe(15000);
    expect($order->change_amount)->toBe(0);
    expect($order->midtrans_order_id)->toBe($orderId);
    expect($bill->status)->toBe('PAID');
    expect($bill->midtrans_order_id)->toBe($orderId);
});

// ── P2. repeated POS settlement is idempotent ────────────────────────────────
test('P2: repeated POS settlement is idempotent', function () {
    [$order, $bill] = posDebtOrder(15000);
    $orderId = "POS-{$order->id}-abc123-" . time();
    $payload = posWebhook($orderId, 15000, 'settlement');

    $this->postJson('/api/midtrans-callback', $payload)->assertOk();
    $paidAtBill      = $bill->fresh()->paid_at;
    $auditAfterFirst = AuditLog::where('action', AuditLog::PAYMENT_CONFIRMED)->count();

    // Replay the same settlement several times.
    $this->postJson('/api/midtrans-callback', $payload)->assertOk();
    $this->postJson('/api/midtrans-callback', $payload)->assertOk();
    $this->postJson('/api/midtrans-callback', $payload)->assertOk();

    expect($order->fresh()->payment_status)->toBe('PAID');
    // Bill paid_at not changed by replays.
    expect($bill->fresh()->paid_at->toDateTimeString())->toBe($paidAtBill->toDateTimeString());
    // No duplicate PAYMENT_CONFIRMED audit.
    expect(AuditLog::where('action', AuditLog::PAYMENT_CONFIRMED)->count())->toBe($auditAfterFirst);
});

// ── P3. POS amount mismatch → not settled ────────────────────────────────────
test('P3: POS amount mismatch does not settle order or KANTIN bill', function () {
    [$order, $bill] = posDebtOrder(15000);
    $orderId = "POS-{$order->id}-abc123-" . time();

    $this->postJson('/api/midtrans-callback', posWebhook($orderId, 10000, 'settlement'))
         ->assertOk();

    expect($order->fresh()->payment_status)->toBe('UNPAID');
    expect($bill->fresh()->status)->toBe('UNPAID');
});

// ── P4. invalid signature → nothing mutates ──────────────────────────────────
test('P4: POS callback with invalid signature mutates nothing', function () {
    [$order, $bill] = posDebtOrder(15000);
    $orderId = "POS-{$order->id}-abc123-" . time();
    $payload = posWebhook($orderId, 15000, 'settlement');
    $payload['signature_key'] = 'forged';

    $this->postJson('/api/midtrans-callback', $payload)->assertOk();

    expect($order->fresh()->payment_status)->toBe('UNPAID');
    expect($bill->fresh()->status)->toBe('UNPAID');
});

// ── P5. nonexistent order → 200, nothing mutates ─────────────────────────────
test('P5: POS callback for nonexistent order mutates nothing', function () {
    $orderId = 'POS-999999-abc123-' . time();

    $this->postJson('/api/midtrans-callback', posWebhook($orderId, 15000, 'settlement'))
         ->assertOk();

    expect(AuditLog::where('action', AuditLog::PAYMENT_CONFIRMED)->count())->toBe(0);
});

// ── P6. already-PAID (cash) order receiving provider callback is a no-op ─────
test('P6: provider callback on a cash-PAID order is a no-op', function () {
    $student = Student::factory()->create();
    $order   = PosOrder::create([
        'user_id'           => null,
        'student_id'        => $student->id,
        'transaction_code'  => 'TRX-' . time() . strtoupper(\Illuminate\Support\Str::random(3)),
        'total_amount'      => 15000,
        'payment_amount'    => 20000,
        'change_amount'     => 5000,
        'payment_status'    => 'PAID',          // paid cash at counter
        'redemption_status' => 'COMPLETED',
    ]);

    $orderId = "POS-{$order->id}-abc123-" . time();
    $this->postJson('/api/midtrans-callback', posWebhook($orderId, 15000, 'settlement'))
         ->assertOk();

    $order->refresh();
    // Cash-settled values must NOT be overwritten by the provider callback.
    expect($order->payment_amount)->toBe(20000);
    expect($order->change_amount)->toBe(5000);
    expect($order->midtrans_order_id)->toBeNull();
});

// ── P7. settlement writes only real columns (no phantom paid_amount) ─────────
test('P7: POS settlement persists without SQL error (regression for phantom columns)', function () {
    [$order, $bill] = posDebtOrder(20000);
    $orderId = "POS-{$order->id}-abc123-" . time();

    $this->postJson('/api/midtrans-callback', posWebhook($orderId, 20000, 'settlement'))
         ->assertOk();

    // If the phantom `paid_amount`/`payment_method` write were still present,
    // the update would have thrown and payment_status would remain UNPAID.
    expect($order->fresh()->payment_status)->toBe('PAID');
});
