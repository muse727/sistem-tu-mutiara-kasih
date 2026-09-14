<?php

/**
 * Phase 09D.2 — TU transactional outbox tests.
 *
 * Proves: source commit + outbox commit are atomic; duplicate facts create one
 * event; events conform to Contract v1; rollback creates no event; the
 * dispatcher claims/retries safely; payload is immutable. Finance is NOT
 * contacted — the Null transport is used.
 */

use App\Models\IntegrationOutboxEvent;
use App\Models\PosOrder;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\BillingPaymentService;
use App\Services\Integration\IntegrationOutboxService;
use App\Services\Integration\OutboxDispatcher;
use App\Services\Integration\TuFinanceContractValidator;
use Illuminate\Support\Facades\DB;

function outboxUser(): User
{
    return User::factory()->create(['role' => 'tu']);
}

function outboxBill(array $overrides = []): StudentBill
{
    return StudentBill::factory()->create(array_merge([
        'student_id' => Student::factory()->create(['status' => 'active'])->id,
        'type' => 'SPP', 'status' => 'UNPAID', 'payment_method' => 'CASH',
    ], $overrides));
}

// Phase 09D.4: these tests exercise an ENABLED integration (no activation
// boundary), so all newly settled SPP bills are eligible.
beforeEach(function () {
    config(['integration.enabled' => true, 'integration.activated_at' => null]);
});

// ── SPP ───────────────────────────────────────────────────────────────────────

test('cash SPP payment creates exactly one spp.payment outbox event atomically', function () {
    $bill = outboxBill();
    app(BillingPaymentService::class)->payCash($bill, outboxUser()->id);

    $events = IntegrationOutboxEvent::where('event_type', 'spp.payment')->get();
    expect($events)->toHaveCount(1);

    $e = $events->first();
    expect($e->source_record_id)->toBe((string) $bill->id)
        ->and($e->idempotency_key)->toBe("TU:spp.payment:{$bill->id}")
        ->and($e->delivery_status)->toBe('PENDING')
        ->and($e->payload['student']['student_uuid'])->toBe($bill->student->student_uuid)
        ->and($e->payload['bill_type'])->toBe('SPP')
        ->and($e->payload['payment_method'])->toBe('CASH')
        ->and($e->payload['provider'])->toBeNull();
});

test('SPP outbox event conforms to Contract v1', function () {
    $bill = outboxBill();
    app(BillingPaymentService::class)->payCash($bill, outboxUser()->id);
    $e = IntegrationOutboxEvent::where('event_type', 'spp.payment')->firstOrFail();

    $event = [
        'event_id' => $e->event_id, 'event_type' => $e->event_type,
        'event_version' => $e->event_version, 'contract_version' => $e->contract_version,
        'source_system' => $e->source_system, 'source_record_id' => $e->source_record_id,
        'idempotency_key' => $e->idempotency_key,
        'occurred_at' => \Carbon\Carbon::parse($e->occurred_at)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i:sP'),
        'payload' => $e->payload,
    ];
    $errors = TuFinanceContractValidator::validate($event);
    expect($errors)->toBe([]);
});

test('duplicate SPP settlement does not create a second outbox event', function () {
    $bill = outboxBill();
    $svc = app(BillingPaymentService::class);
    $svc->payCash($bill, outboxUser()->id);

    // A duplicate settlement attempt throws (already PAID) — no second event.
    try {
        $svc->payCash($bill->refresh(), outboxUser()->id);
    } catch (\RuntimeException) {
    }
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(1);
});

test('midtrans SPP settlement creates event with provider reference', function () {
    $bill = outboxBill(['midtrans_order_id' => 'BILL-1-abc']);
    app(BillingPaymentService::class)->handleMidtransSuccess('BILL-1-abc');

    $e = IntegrationOutboxEvent::where('event_type', 'spp.payment')->firstOrFail();
    expect($e->payload['provider'])->toBe('midtrans')
        ->and($e->payload['provider_reference'])->toBe('BILL-1-abc')
        ->and($e->payload['payment_method'])->toBe('MIDTRANS');
});

test('duplicate midtrans callback does not create a second event', function () {
    $bill = outboxBill(['midtrans_order_id' => 'BILL-2-xyz']);
    $svc = app(BillingPaymentService::class);
    $svc->handleMidtransSuccess('BILL-2-xyz');
    $svc->handleMidtransSuccess('BILL-2-xyz'); // duplicate webhook
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(1);
});

test('source rollback leaves no outbox event and bill unchanged', function () {
    $bill = outboxBill();
    try {
        DB::transaction(function () use ($bill) {
            $bill->update(['status' => 'PAID', 'paid_at' => now(), 'payment_method' => 'CASH']);
            app(IntegrationOutboxService::class)->recordSppPayment($bill);
            throw new \RuntimeException('force rollback');
        });
    } catch (\RuntimeException) {
    }

    expect(IntegrationOutboxEvent::count())->toBe(0)
        ->and($bill->refresh()->status)->toBe('UNPAID');
});

// ── KANTIN (out of scope, Phase 09D.6-FIX) ───────────────────────────────────

test('KANTIN bill settlement emits NO Finance integration event (out of scope)', function () {
    $student = Student::factory()->create(['status' => 'active']);
    $order = PosOrder::create([
        'user_id' => outboxUser()->id, 'student_id' => $student->id,
        'transaction_code' => 'TRX-K-1', 'total_amount' => 8000,
        'payment_amount' => 0, 'change_amount' => 0,
        'payment_status' => 'UNPAID', 'redemption_status' => 'PENDING', 'qr_token' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    $bill = StudentBill::factory()->create([
        'student_id' => $student->id, 'pos_order_id' => $order->id,
        'type' => 'KANTIN', 'status' => 'UNPAID', 'payment_method' => 'CASH', 'amount' => 8000,
    ]);

    app(BillingPaymentService::class)->payCash($bill, outboxUser()->id);

    // KANTIN debt settlement is a TU-domain concern only — no integration event.
    expect($bill->refresh()->status)->toBe('PAID')
        ->and(IntegrationOutboxEvent::count())->toBe(0);
});

// ── POS cash ──────────────────────────────────────────────────────────────────

test('POS cash sale creates one pos.cash_sale event', function () {
    $order = PosOrder::create([
        'user_id' => outboxUser()->id, 'student_id' => null,
        'transaction_code' => 'TRX-C-1', 'total_amount' => 15000,
        'payment_amount' => 20000, 'change_amount' => 5000,
        'payment_status' => 'PAID', 'redemption_status' => 'COMPLETED', 'qr_token' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    app(IntegrationOutboxService::class)->recordPosCashSale($order);

    $e = IntegrationOutboxEvent::where('event_type', 'pos.cash_sale')->firstOrFail();
    expect($e->source_record_id)->toBe((string) $order->id)
        ->and($e->idempotency_key)->toBe("TU:pos.cash_sale:{$order->id}")
        ->and($e->payload['transaction_code'])->toBe('TRX-C-1')
        ->and($e->payload['payment_method'])->toBe('CASH')
        ->and($e->payload['provider'])->toBeNull()
        ->and($e->payload['payment_status'])->toBe('PAID')
        ->and($e->payload['amount'])->toBe(15000);
});

test('POS cash event conforms to Contract v1', function () {
    $order = PosOrder::create([
        'user_id' => outboxUser()->id, 'student_id' => null, 'transaction_code' => 'TRX-C-2',
        'total_amount' => 5000, 'payment_amount' => 5000, 'change_amount' => 0,
        'payment_status' => 'PAID', 'redemption_status' => 'COMPLETED', 'qr_token' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    app(IntegrationOutboxService::class)->recordPosCashSale($order);
    $e = IntegrationOutboxEvent::where('event_type', 'pos.cash_sale')->firstOrFail();

    $event = [
        'event_id' => $e->event_id, 'event_type' => $e->event_type, 'event_version' => $e->event_version,
        'contract_version' => $e->contract_version, 'source_system' => $e->source_system,
        'source_record_id' => $e->source_record_id, 'idempotency_key' => $e->idempotency_key,
        'occurred_at' => \Carbon\Carbon::parse($e->occurred_at)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i:sP'),
        'payload' => $e->payload,
    ];
    expect(TuFinanceContractValidator::validate($event))->toBe([]);
});

test('unpaid POS order emits no event', function () {
    $order = PosOrder::create([
        'user_id' => outboxUser()->id, 'student_id' => null, 'transaction_code' => 'TRX-C-3',
        'total_amount' => 5000, 'payment_amount' => 0, 'change_amount' => 0,
        'payment_status' => 'UNPAID', 'redemption_status' => 'PENDING', 'qr_token' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    expect(app(IntegrationOutboxService::class)->recordPosCashSale($order))->toBeNull();
    expect(IntegrationOutboxEvent::count())->toBe(0);
});

// ── Idempotency / uniqueness ──────────────────────────────────────────────────

test('database enforces one canonical event per source fact', function () {
    $order = PosOrder::create([
        'user_id' => outboxUser()->id, 'student_id' => null, 'transaction_code' => 'TRX-C-4',
        'total_amount' => 1000, 'payment_amount' => 1000, 'change_amount' => 0,
        'payment_status' => 'PAID', 'redemption_status' => 'COMPLETED', 'qr_token' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    $svc = app(IntegrationOutboxService::class);
    $svc->recordPosCashSale($order);
    $svc->recordPosCashSale($order); // replay → firstOrCreate, no second row
    expect(IntegrationOutboxEvent::where('event_type', 'pos.cash_sale')->count())->toBe(1);
});

test('different event types on same source record remain independent', function () {
    $student = Student::factory()->create(['status' => 'active']);
    $order = PosOrder::create([
        'user_id' => outboxUser()->id, 'student_id' => $student->id, 'transaction_code' => 'TRX-M-1',
        'total_amount' => 3000, 'payment_amount' => 3000, 'change_amount' => 0,
        'payment_status' => 'PAID', 'redemption_status' => 'COMPLETED', 'qr_token' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    // SPP bill (type=SPP) and POS cash sale on the same student are independent events.
    $bill = StudentBill::factory()->create([
        'student_id' => $student->id, 'pos_order_id' => null, 'type' => 'SPP',
        'status' => 'PAID', 'payment_method' => 'CASH', 'amount' => 3000, 'paid_at' => now(),
    ]);
    $svc = app(IntegrationOutboxService::class);
    $svc->recordPosCashSale($order);
    $svc->recordSppPayment($bill);
    expect(IntegrationOutboxEvent::count())->toBe(2)
        ->and(IntegrationOutboxEvent::where('event_type', 'pos.cash_sale')->count())->toBe(1)
        ->and(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(1);
});

// ── Immutability ─────────────────────────────────────────────────────────────

test('event identity and payload are immutable after creation', function () {
    $order = PosOrder::create([
        'user_id' => outboxUser()->id, 'student_id' => null, 'transaction_code' => 'TRX-C-5',
        'total_amount' => 2000, 'payment_amount' => 2000, 'change_amount' => 0,
        'payment_status' => 'PAID', 'redemption_status' => 'COMPLETED', 'qr_token' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    $e = app(IntegrationOutboxService::class)->recordPosCashSale($order);

    $e->payload = ['tampered' => true];
    expect(fn () => $e->save())->toThrow(\LogicException::class);
});

// ── Dispatcher ────────────────────────────────────────────────────────────────

test('dispatcher with null transport marks events retryable and preserves source truth', function () {
    $bill = outboxBill();
    app(BillingPaymentService::class)->payCash($bill, outboxUser()->id);

    $result = app(OutboxDispatcher::class)->dispatch(1);
    expect($result['claimed'])->toBe(1)->and($result['retryable'])->toBe(1);

    $e = IntegrationOutboxEvent::first();
    expect($e->delivery_status)->toBe('FAILED_RETRYABLE')
        ->and($e->attempt_count)->toBe(1)
        ->and($bill->refresh()->status)->toBe('PAID'); // source unaffected
});

test('dispatcher increments attempt count and stops at max', function () {
    $order = PosOrder::create([
        'user_id' => outboxUser()->id, 'student_id' => null, 'transaction_code' => 'TRX-C-6',
        'total_amount' => 1000, 'payment_amount' => 1000, 'change_amount' => 0,
        'payment_status' => 'PAID', 'redemption_status' => 'COMPLETED', 'qr_token' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    app(IntegrationOutboxService::class)->recordPosCashSale($order);
    $dispatcher = app(OutboxDispatcher::class);

    for ($i = 0; $i < OutboxDispatcher::MAX_ATTEMPTS; $i++) {
        $dispatcher->dispatch(1);
    }
    $e = IntegrationOutboxEvent::first();
    expect($e->attempt_count)->toBe(OutboxDispatcher::MAX_ATTEMPTS)
        ->and($e->delivery_status)->toBe('FAILED_PERMANENT');
});

test('payload snapshot is stable across retry', function () {
    $order = PosOrder::create([
        'user_id' => outboxUser()->id, 'student_id' => null, 'transaction_code' => 'TRX-C-7',
        'total_amount' => 7000, 'payment_amount' => 7000, 'change_amount' => 0,
        'payment_status' => 'PAID', 'redemption_status' => 'COMPLETED', 'qr_token' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    app(IntegrationOutboxService::class)->recordPosCashSale($order);
    $dispatcher = app(OutboxDispatcher::class);
    $dispatcher->dispatch(1);
    $payloadAfterFirst = IntegrationOutboxEvent::first()->payload;
    $dispatcher->dispatch(1);
    expect(IntegrationOutboxEvent::first()->payload)->toBe($payloadAfterFirst);
});
