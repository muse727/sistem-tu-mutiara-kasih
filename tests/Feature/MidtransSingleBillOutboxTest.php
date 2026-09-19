<?php

/**
 * F-3 — SINGLE-bill Midtrans settlement emits the Finance spp.payment outbox.
 *
 * Verified callback → PaymentAttempt settled + StudentBill PAID + audit +
 * ONE spp.payment outbox event, atomically. Idempotent against duplicates.
 */

use App\Models\IntegrationOutboxEvent;
use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Support\Facades\Config;

function f3Payload(string $orderId, string $grossAmount, string $serverKey, array $overrides = []): array {
    $statusCode = $overrides['status_code'] ?? '200';
    $signature  = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
    return array_merge([
        'order_id'           => $orderId,
        'status_code'        => $statusCode,
        'gross_amount'       => $grossAmount,
        'transaction_status' => 'settlement',
        'fraud_status'       => 'accept',
        'payment_type'       => 'bank_transfer',
        'signature_key'      => $signature,
    ], $overrides);
}

function f3BillWithAttempt(array $billAttrs = []): array {
    $student = Student::factory()->create();
    $bill    = StudentBill::factory()->create(array_merge([
        'student_id' => $student->id,
        'status'     => 'UNPAID',
        'type'       => 'SPP',
        'amount'     => 600000,
    ], $billAttrs));

    $orderId = 'BILL-' . $bill->id . '-abc123-' . time();
    $attempt = PaymentAttempt::factory()->create([
        'student_bill_id' => $bill->id,
        'provider'        => 'MIDTRANS',
        'order_id'        => $orderId,
        'status'          => PaymentAttempt::STATUS_PENDING,
        'gross_amount'    => $bill->amount,
    ]);
    // The factory's provider_reference is its own generated order_id; align it.
    $attempt->update(['provider_reference' => $orderId]);
    $bill->update(['midtrans_order_id' => $orderId]);

    return [$bill, $orderId];
}

beforeEach(function () {
    Config::set('services.midtrans.server_key', 'test-server-key-xyz');
    Config::set('integration.enabled', true);
    Config::set('integration.activated_at', null);
});

// ── 1. SUCCESS emits exactly one spp.payment outbox event ────────────────────
test('single-bill midtrans SUCCESS emits one spp.payment outbox event', function () {
    [$bill, $orderId] = f3BillWithAttempt();

    $this->postJson('/siswa/payment/callback', f3Payload($orderId, '600000', 'test-server-key-xyz'))
         ->assertOk();

    expect($bill->fresh()->status)->toBe('PAID');

    $events = IntegrationOutboxEvent::where('event_type', 'spp.payment')->get();
    expect($events)->toHaveCount(1);

    $e = $events->first();
    expect($e->source_system)->toBe('TU')
        ->and($e->source_record_id)->toBe((string) $bill->id)
        ->and($e->idempotency_key)->toBe("TU:spp.payment:{$bill->id}");
});

// ── 2. Outbox payload matches the established SPP contract ──────────────────
test('outbox payload follows the existing spp.payment contract (provider=midtrans)', function () {
    [$bill, $orderId] = f3BillWithAttempt();

    $this->postJson('/siswa/payment/callback', f3Payload($orderId, '600000', 'test-server-key-xyz'))
         ->assertOk();

    $e = IntegrationOutboxEvent::where('event_type', 'spp.payment')->firstOrFail();
    $p = $e->payload;

    expect($p['bill_id'])->toBe($bill->id)
        ->and($p['bill_type'])->toBe('SPP')
        ->and((float) $p['amount'])->toBe(600000.0)   // authoritative bill amount
        ->and($p['payment_method'])->toBe('MIDTRANS')
        ->and($p['provider'])->toBe('midtrans')
        ->and($p['provider_reference'])->toBe($orderId)
        ->and($p['paid_at'])->not->toBeEmpty();
});

// ── 3. Duplicate SUCCESS callback → no duplicate outbox event ───────────────
test('duplicate SUCCESS callback does not create a duplicate outbox event', function () {
    [$bill, $orderId] = f3BillWithAttempt();
    $payload = f3Payload($orderId, '600000', 'test-server-key-xyz');

    $this->postJson('/siswa/payment/callback', $payload)->assertOk();
    $this->postJson('/siswa/payment/callback', $payload)->assertOk();

    expect($bill->fresh()->status)->toBe('PAID');
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(1);
});

// ── 4. Outbox failure rolls back settlement (no partial PAID) ───────────────
test('outbox failure rolls back the settlement transaction', function () {
    [$bill, $orderId] = f3BillWithAttempt();

    // Force recordSppPayment to throw inside the transaction.
    $this->app->bind(\App\Services\Integration\IntegrationOutboxService::class, function () {
        return new class extends \App\Services\Integration\IntegrationOutboxService {
            public function recordSppPayment(\App\Models\StudentBill $bill): ?\App\Models\IntegrationOutboxEvent {
                throw new \RuntimeException('simulated outbox failure');
            }
        };
    });

    $this->postJson('/siswa/payment/callback', f3Payload($orderId, '600000', 'test-server-key-xyz'));

    // Entire settlement must have rolled back — no partial success.
    expect($bill->fresh()->status)->toBe('UNPAID');
    expect(PaymentAttempt::where('order_id', $orderId)->first()->status)->toBe('pending');
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(0);
});

// ── 5. KANTIN bill settles but emits NO spp.payment event (gate preserved) ──
test('KANTIN single-bill settlement does not emit spp.payment (gate intact)', function () {
    [$bill, $orderId] = f3BillWithAttempt(['type' => 'KANTIN']);

    $this->postJson('/siswa/payment/callback', f3Payload($orderId, '600000', 'test-server-key-xyz'))
         ->assertOk();

    expect($bill->fresh()->status)->toBe('PAID');
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(0);
});

// ── 6. Integration disabled → settles PAID but no outbox (gate intact) ──────
test('integration disabled → bill still settles PAID, no outbox event', function () {
    Config::set('integration.enabled', false);
    [$bill, $orderId] = f3BillWithAttempt();

    $this->postJson('/siswa/payment/callback', f3Payload($orderId, '600000', 'test-server-key-xyz'))
         ->assertOk();

    expect($bill->fresh()->status)->toBe('PAID');
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(0);
});
