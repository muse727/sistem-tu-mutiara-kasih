<?php

/**
 * MULTI-02 — MULTI-bill Midtrans settlement emits one Finance spp.payment
 * outbox event per settled bill.
 *
 * Closes the gap found in MULTI-01: PaymentSiswaController::handleMultiCallback()
 * settled each bill + PaymentAttempt but never called recordSppPayment(), so a
 * MULTI payment produced bills=PAID with zero Finance outbox events.
 *
 * Covered scenarios:
 *   T1. valid MULTI settlement emits exactly one spp.payment per bill
 *   T2. each event amount equals its own bill amount (never the MULTI total)
 *   T3. all events share the MULTI order_id as provider_reference
 *   T4. event source_record_id matches the individual bill
 *   T5. canonical idempotency key TU:spp.payment:{bill.id} is preserved
 *   T6. duplicate MULTI SUCCESS callback creates no duplicate events
 *   T7. outbox failure for one bill rolls back the ENTIRE MULTI settlement
 *   T8. KANTIN bill in MULTI settles PAID but emits NO spp.payment (gate intact)
 *   T9. integration disabled → bills settle PAID but no outbox events (gate intact)
 */

use App\Models\IntegrationOutboxEvent;
use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Support\Facades\Config;

/** Build a signed Midtrans webhook payload for a MULTI order. */
function m02Webhook(string $orderId, float $amount, string $status, string $serverKey = 'test-server-key-xyz'): array
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
        'payment_type'       => 'bank_transfer',
        'transaction_id'     => 'm02-txn-' . substr(md5($orderId), 0, 8),
        'settlement_time'    => now()->format('Y-m-d H:i:s'),
        'signature_key'      => $signature,
    ];
}

/** Build N UNPAID SPP bills (owning student) sharing one MULTI order_id + pending attempts. */
function m02MultiBills(array $amounts, string $orderId): array
{
    $student = Student::factory()->create();
    $bills = [];
    foreach ($amounts as $amt) {
        $bill = StudentBill::factory()->create([
            'student_id'        => $student->id,
            'status'            => 'UNPAID',
            'type'              => 'SPP',
            'amount'            => $amt,
            'midtrans_order_id' => $orderId,
        ]);
        PaymentAttempt::factory()->create([
            'student_bill_id' => $bill->id,
            'provider'        => 'MIDTRANS',
            'order_id'        => $orderId,
            'status'          => PaymentAttempt::STATUS_PENDING,
            'gross_amount'    => $bill->amount,
        ]);
        $bills[] = $bill;
    }
    return $bills;
}

beforeEach(function () {
    Config::set('services.midtrans.server_key', 'test-server-key-xyz');
    Config::set('integration.enabled', true);
    Config::set('integration.activated_at', null);
});

// ── T1. one spp.payment event per bill ──────────────────────────────────────
test('T1: MULTI settlement emits exactly one spp.payment per bill', function () {
    $orderId = 'MULTI-1_2_3-m02abc-' . time();
    $bills = m02MultiBills([500000, 500000, 500000, 500000], $orderId);
    // Re-key the order id to the actual bill ids.
    $orderId = 'MULTI-' . implode('_', array_map(fn ($b) => $b->id, $bills)) . '-m02abc-' . time();
    foreach ($bills as $b) {
        $b->update(['midtrans_order_id' => $orderId]);
        PaymentAttempt::where('student_bill_id', $b->id)->update(['order_id' => $orderId]);
    }
    $total = array_sum(array_map(fn ($b) => $b->amount, $bills));

    $this->postJson('/siswa/payment/callback', m02Webhook($orderId, $total, 'settlement'))
         ->assertOk();

    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(4);
});

// ── T2. each event amount equals its own bill amount ────────────────────────
test('T2: each spp.payment event amount equals its own bill amount', function () {
    $amounts = [500000, 250000, 750000];
    $bills = m02MultiBills($amounts, 'tmp');
    $orderId = 'MULTI-' . implode('_', array_map(fn ($b) => $b->id, $bills)) . '-m02amt-' . time();
    foreach ($bills as $b) {
        $b->update(['midtrans_order_id' => $orderId]);
        PaymentAttempt::where('student_bill_id', $b->id)->update(['order_id' => $orderId]);
    }
    $total = array_sum($amounts);

    $this->postJson('/siswa/payment/callback', m02Webhook($orderId, $total, 'settlement'))
         ->assertOk();

    foreach ($bills as $i => $b) {
        $e = IntegrationOutboxEvent::where('event_type', 'spp.payment')
                                   ->where('source_record_id', (string) $b->id)
                                   ->first();
        expect($e)->not->toBeNull();
        expect((string) $e->payload['amount'])->toBe(number_format($amounts[$i], 2, '.', ''));
    }
});

// ── T3. all events share the MULTI provider_reference/order_id ──────────────
test('T3: all spp.payment events share the MULTI order_id as provider_reference', function () {
    $bills = m02MultiBills([100000, 200000], 'tmp');
    $orderId = 'MULTI-' . implode('_', array_map(fn ($b) => $b->id, $bills)) . '-m02ref-' . time();
    foreach ($bills as $b) {
        $b->update(['midtrans_order_id' => $orderId]);
        PaymentAttempt::where('student_bill_id', $b->id)->update(['order_id' => $orderId]);
    }

    $this->postJson('/siswa/payment/callback', m02Webhook($orderId, 300000, 'settlement'))
         ->assertOk();

    $events = IntegrationOutboxEvent::where('event_type', 'spp.payment')->get();
    expect($events)->toHaveCount(2);
    foreach ($events as $e) {
        expect($e->payload['provider_reference'])->toBe($orderId);
        expect($e->payload['provider'])->toBe('midtrans');
    }
});

// ── T4. event source_record_id matches the individual bill ──────────────────
test('T4: each event source_record_id matches its own bill id', function () {
    $bills = m02MultiBills([100000, 200000, 300000], 'tmp');
    $orderId = 'MULTI-' . implode('_', array_map(fn ($b) => $b->id, $bills)) . '-m02sid-' . time();
    foreach ($bills as $b) {
        $b->update(['midtrans_order_id' => $orderId]);
        PaymentAttempt::where('student_bill_id', $b->id)->update(['order_id' => $orderId]);
    }

    $this->postJson('/siswa/payment/callback', m02Webhook($orderId, 600000, 'settlement'))
         ->assertOk();

    $ids = IntegrationOutboxEvent::where('event_type', 'spp.payment')->pluck('source_record_id')->sort()->values()->all();
    $expected = collect($bills)->map(fn ($b) => (string) $b->id)->sort()->values()->all();
    expect($ids)->toBe($expected);
});

// ── T5. canonical idempotency key preserved ─────────────────────────────────
test('T5: event idempotency key is TU:spp.payment:{bill.id}', function () {
    $bills = m02MultiBills([100000, 200000], 'tmp');
    $orderId = 'MULTI-' . implode('_', array_map(fn ($b) => $b->id, $bills)) . '-m02key-' . time();
    foreach ($bills as $b) {
        $b->update(['midtrans_order_id' => $orderId]);
        PaymentAttempt::where('student_bill_id', $b->id)->update(['order_id' => $orderId]);
    }

    $this->postJson('/siswa/payment/callback', m02Webhook($orderId, 300000, 'settlement'))
         ->assertOk();

    foreach ($bills as $b) {
        $e = IntegrationOutboxEvent::where('event_type', 'spp.payment')
                                   ->where('source_record_id', (string) $b->id)
                                   ->firstOrFail();
        expect($e->source_system)->toBe('TU');
        expect($e->idempotency_key)->toBe("TU:spp.payment:{$b->id}");
    }
});

// ── T6. duplicate callback → no duplicate events ────────────────────────────
test('T6: duplicate MULTI settlement creates no duplicate events', function () {
    $bills = m02MultiBills([100000, 200000, 300000], 'tmp');
    $orderId = 'MULTI-' . implode('_', array_map(fn ($b) => $b->id, $bills)) . '-m02dup-' . time();
    foreach ($bills as $b) {
        $b->update(['midtrans_order_id' => $orderId]);
        PaymentAttempt::where('student_bill_id', $b->id)->update(['order_id' => $orderId]);
    }
    $payload = m02Webhook($orderId, 600000, 'settlement');

    $this->postJson('/siswa/payment/callback', $payload)->assertOk();
    $this->postJson('/siswa/payment/callback', $payload)->assertOk();

    foreach ($bills as $b) {
        expect($b->fresh()->status)->toBe('PAID');
    }
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(3);
});

// ── T7. outbox failure rolls back the entire MULTI settlement ───────────────
test('T7: outbox failure rolls back the entire MULTI settlement', function () {
    $bills = m02MultiBills([100000, 200000], 'tmp');
    $orderId = 'MULTI-' . implode('_', array_map(fn ($b) => $b->id, $bills)) . '-m02rb-' . time();
    foreach ($bills as $b) {
        $b->update(['midtrans_order_id' => $orderId]);
        PaymentAttempt::where('student_bill_id', $b->id)->update(['order_id' => $orderId]);
    }

    // Force recordSppPayment to throw inside the settlement transaction.
    $this->app->bind(\App\Services\Integration\IntegrationOutboxService::class, function () {
        return new class extends \App\Services\Integration\IntegrationOutboxService {
            public function recordSppPayment(\App\Models\StudentBill $bill): ?\App\Models\IntegrationOutboxEvent
            {
                throw new \RuntimeException('simulated outbox failure');
            }
        };
    });

    try {
        $this->postJson('/siswa/payment/callback', m02Webhook($orderId, 300000, 'settlement'));
    } catch (\Throwable $e) {
        // exception may propagate; the assertion below is what matters
    }

    foreach ($bills as $b) {
        expect($b->fresh()->status)->toBe('UNPAID');
        $attempt = PaymentAttempt::where('student_bill_id', $b->id)->where('order_id', $orderId)->first();
        expect($attempt->status)->toBe(PaymentAttempt::STATUS_PENDING);
    }
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(0);
});

// ── T8. KANTIN bill in MULTI settles PAID but emits NO spp.payment ──────────
test('T8: KANTIN bill in MULTI settles PAID but emits no spp.payment', function () {
    $student = Student::factory()->create();
    $spp = StudentBill::factory()->create([
        'student_id' => $student->id, 'status' => 'UNPAID', 'type' => 'SPP', 'amount' => 100000,
    ]);
    $kantin = StudentBill::factory()->create([
        'student_id' => $student->id, 'status' => 'UNPAID', 'type' => 'KANTIN', 'amount' => 25000,
    ]);
    $orderId = 'MULTI-' . $spp->id . '_' . $kantin->id . '-m02k-' . time();
    foreach ([$spp, $kantin] as $b) {
        $b->update(['midtrans_order_id' => $orderId]);
        PaymentAttempt::factory()->create([
            'student_bill_id' => $b->id, 'provider' => 'MIDTRANS', 'order_id' => $orderId,
            'status' => PaymentAttempt::STATUS_PENDING, 'gross_amount' => $b->amount,
        ]);
    }

    $this->postJson('/siswa/payment/callback', m02Webhook($orderId, 125000, 'settlement'))
         ->assertOk();

    expect($spp->fresh()->status)->toBe('PAID');
    expect($kantin->fresh()->status)->toBe('PAID');

    // Only the SPP bill emitted an event; KANTIN did not.
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(1);
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')
        ->where('source_record_id', (string) $spp->id)->count())->toBe(1);
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')
        ->where('source_record_id', (string) $kantin->id)->count())->toBe(0);
});

// ── T9. integration disabled → bills settle PAID but no outbox events ───────
test('T9: integration disabled → bills settle PAID but no outbox events', function () {
    Config::set('integration.enabled', false);
    $bills = m02MultiBills([100000, 200000], 'tmp');
    $orderId = 'MULTI-' . implode('_', array_map(fn ($b) => $b->id, $bills)) . '-m02off-' . time();
    foreach ($bills as $b) {
        $b->update(['midtrans_order_id' => $orderId]);
        PaymentAttempt::where('student_bill_id', $b->id)->update(['order_id' => $orderId]);
    }

    $this->postJson('/siswa/payment/callback', m02Webhook($orderId, 300000, 'settlement'))
         ->assertOk();

    foreach ($bills as $b) {
        expect($b->fresh()->status)->toBe('PAID');
    }
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(0);
});
