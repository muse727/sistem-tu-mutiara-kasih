<?php

/**
 * Phase 09D.7 — OUTBOX state-transition certification.
 *
 * Certifies the full outbox lifecycle in one place: PENDING → PROCESSING →
 * DELIVERED, PROCESSING → FAILED_RETRYABLE → (retry) → DELIVERED, and
 * PROCESSING → FAILED_PERMANENT, plus identity/payload immutability across
 * delivery. Uses Http::fake; no real network.
 */

use App\Models\IntegrationOutboxEvent;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\BillingPaymentService;
use App\Services\Integration\OutboxDispatcher;
use Illuminate\Support\Facades\Http;

function cert7Bill(): StudentBill
{
    return StudentBill::factory()->create([
        'student_id' => Student::factory()->create(['status' => 'active'])->id,
        'type' => 'SPP', 'status' => 'UNPAID', 'payment_method' => 'CASH', 'amount' => 150000,
    ]);
}

beforeEach(function () {
    config(['integration.enabled' => true, 'integration.activated_at' => null]);
    config(['integration.finance.url' => 'https://finance.test/api/integration/tu/events']);
    config(['integration.finance.secret' => 'test-tu-secret']);
    config(['integration.finance.key_id' => 'tu-local']);
});

test('certify: PENDING → PROCESSING → DELIVERED on 2xx', function () {
    Http::fake(fn () => Http::response(['result' => 'PROCESSED'], 201));
    $bill = cert7Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);

    $e = IntegrationOutboxEvent::firstOrFail();
    expect($e->delivery_status)->toBe('PENDING'); // before dispatch

    app(OutboxDispatcher::class)->dispatch(1);
    expect($e->refresh()->delivery_status)->toBe('DELIVERED')
        ->and($e->attempt_count)->toBe(1)
        ->and($e->delivered_at)->not->toBeNull();
});

test('certify: PROCESSING → FAILED_RETRYABLE → (retry) → DELIVERED', function () {
    $call = 0;
    Http::fake(function () use (&$call) {
        $call++;
        return $call === 1 ? Http::response('err', 500) : Http::response(['result' => 'PROCESSED'], 201);
    });
    $bill = cert7Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);

    app(OutboxDispatcher::class)->dispatch(1); // 500
    $e = IntegrationOutboxEvent::firstOrFail();
    expect($e->refresh()->delivery_status)->toBe('FAILED_RETRYABLE')
        ->and($e->last_error)->not->toBeNull();

    app(OutboxDispatcher::class)->dispatch(1); // success
    expect($e->refresh()->delivery_status)->toBe('DELIVERED')
        ->and($e->attempt_count)->toBe(2);
});

test('certify: PROCESSING → FAILED_PERMANENT on 4xx, no endless retry', function () {
    Http::fake(fn () => Http::response(['code' => 'MAPPING_REQUIRED'], 422));
    $bill = cert7Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);

    app(OutboxDispatcher::class)->dispatch(1);
    $e = IntegrationOutboxEvent::firstOrFail();
    expect($e->refresh()->delivery_status)->toBe('FAILED_PERMANENT');

    // Dispatching again must NOT retry a permanently-failed event.
    app(OutboxDispatcher::class)->dispatch(1);
    expect($e->refresh()->attempt_count)->toBe(1);
});

test('certify: event identity/payload immutable across delivery; only delivery metadata changes', function () {
    $call = 0;
    Http::fake(function () use (&$call) {
        $call++;
        return $call === 1 ? Http::response('err', 500) : Http::response(['result' => 'PROCESSED'], 201);
    });
    $bill = cert7Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);

    $before = IntegrationOutboxEvent::firstOrFail();
    $identity = [
        $before->event_id, $before->event_type, $before->source_record_id,
        $before->idempotency_key, json_encode($before->payload),
        $before->occurred_at?->format('Y-m-d H:i:s'),
    ];

    app(OutboxDispatcher::class)->dispatch(1); // 500
    app(OutboxDispatcher::class)->dispatch(1); // success

    $after = $before->refresh();
    expect([
        $after->event_id, $after->event_type, $after->source_record_id,
        $after->idempotency_key, json_encode($after->payload),
        $after->occurred_at?->format('Y-m-d H:i:s'),
    ])->toBe($identity); // business identity + payload unchanged through retry+delivery
    expect($after->delivery_status)->toBe('DELIVERED'); // only delivery metadata changed
});
