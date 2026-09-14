<?php

/**
 * Phase 09D.5 — POS → Finance forward-only integration tests.
 *
 * Covers pos.cash_sale: activation gate, event shape & contract validity,
 * identity/idempotency, duplicate/timeout/failure handling, and traceability.
 * pos.kantin_payment is OUT OF SCOPE (Phase 09D.6-FIX): the product has no
 * KANTIN Finance integration module; KANTIN debt bills emit no event.
 * Uses Http::fake with the real Finance HMAC canonical spec; no real network.
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
use Illuminate\Support\Facades\Http;

const POS5_SECRET = 'test-tu-secret';
const POS5_PATH = '/api/integration/tu/events';

function pos5Verify($request): bool
{
    $ts = $request->header('X-TU-Timestamp')[0] ?? null;
    $sig = $request->header('X-TU-Signature')[0] ?? null;
    if (! $ts || ! $sig || abs(time() - (int) $ts) > 300) {
        return false;
    }
    $canonical = $ts . "\nPOST\n" . POS5_PATH . "\n" . hash('sha256', $request->body());
    return hash_equals(base64_encode(hash_hmac('sha256', $canonical, POS5_SECRET, true)), $sig);
}

function pos5CashOrder(array $o = []): PosOrder
{
    return PosOrder::create(array_merge([
        'user_id' => User::factory()->create(['role' => 'tu'])->id, 'student_id' => null,
        'transaction_code' => 'TRX-' . uniqid(), 'total_amount' => 15000, 'payment_amount' => 20000,
        'change_amount' => 5000, 'payment_status' => 'PAID', 'redemption_status' => 'COMPLETED',
        'qr_token' => (string) \Illuminate\Support\Str::uuid(),
    ], $o));
}

function toEvent(IntegrationOutboxEvent $e): array
{
    return [
        'event_id' => $e->event_id, 'event_type' => $e->event_type, 'event_version' => $e->event_version,
        'contract_version' => $e->contract_version, 'source_system' => $e->source_system,
        'source_record_id' => $e->source_record_id, 'idempotency_key' => $e->idempotency_key,
        'occurred_at' => \Carbon\Carbon::parse($e->occurred_at)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i:sP'),
        'payload' => $e->payload,
    ];
}

beforeEach(function () {
    config(['integration.enabled' => true, 'integration.activated_at' => null]);
    config(['integration.finance.url' => 'https://finance.test' . POS5_PATH]);
    config(['integration.finance.secret' => POS5_SECRET]);
    config(['integration.finance.key_id' => 'tu-local']);
});

// ── POS cash: generation / contract / identity ───────────────────────────────

test('pos.cash_sale event generated with canonical identity and validates against contract', function () {
    $order = pos5CashOrder();
    app(IntegrationOutboxService::class)->recordPosCashSale($order);
    $e = IntegrationOutboxEvent::where('event_type', 'pos.cash_sale')->firstOrFail();

    expect($e->source_record_id)->toBe((string) $order->id)
        ->and($e->idempotency_key)->toBe("TU:pos.cash_sale:{$order->id}")
        ->and($e->payload['payment_method'])->toBe('CASH')
        ->and($e->payload['payment_status'])->toBe('PAID')
        ->and($e->payload['provider'])->toBeNull()
        ->and($e->payload['provider_reference'])->toBeNull()
        ->and($e->payload['amount'])->toBe(15000);

    expect(TuFinanceContractValidator::validate(toEvent($e)))->toBe([]);
});

test('integration disabled → POS cash sale still works, no outbox event', function () {
    config(['integration.enabled' => false]);
    $order = pos5CashOrder();
    app(IntegrationOutboxService::class)->recordPosCashSale($order);
    expect($order->payment_status)->toBe('PAID')
        ->and(IntegrationOutboxEvent::where('event_type', 'pos.cash_sale')->count())->toBe(0);
});

test('POS cash settled before activation boundary → not integrated', function () {
    config(['integration.activated_at' => '2026-09-13T15:00:00+07:00']);
    $order = pos5CashOrder(['created_at' => \Carbon\Carbon::parse('2026-09-13T14:59:59+07:00')]);
    app(IntegrationOutboxService::class)->recordPosCashSale($order->refresh());
    expect(IntegrationOutboxEvent::where('event_type', 'pos.cash_sale')->count())->toBe(0);
});

test('unpaid POS order → no event (payment truth only, not redemption)', function () {
    $order = pos5CashOrder(['payment_status' => 'UNPAID', 'redemption_status' => 'PENDING']);
    expect(app(IntegrationOutboxService::class)->recordPosCashSale($order))->toBeNull();
    expect(IntegrationOutboxEvent::count())->toBe(0);
});

// ── E2E: dispatch + HMAC + faked Finance ─────────────────────────────────────

test('POS cash E2E: PROCESSED → DELIVERED', function () {
    Http::fake(fn ($r) => pos5Verify($r) ? Http::response(['result' => 'PROCESSED'], 201) : Http::response(['code' => 'INVALID_SIGNATURE'], 401));
    app(IntegrationOutboxService::class)->recordPosCashSale(pos5CashOrder());
    app(OutboxDispatcher::class)->dispatch(1);
    expect(IntegrationOutboxEvent::where('event_type', 'pos.cash_sale')->first()->delivery_status)->toBe('DELIVERED');
});

test('POS cash duplicate delivery → one canonical event, DUPLICATE→DELIVERED', function () {
    $seen = [];
    Http::fake(function ($r) use (&$seen) {
        $k = json_decode($r->body(), true)['idempotency_key'];
        $seen[$k] = ($seen[$k] ?? 0) + 1;
        return Http::response(['result' => $seen[$k] > 1 ? 'DUPLICATE' : 'PROCESSED'], $seen[$k] > 1 ? 200 : 201);
    });
    $order = pos5CashOrder();
    app(IntegrationOutboxService::class)->recordPosCashSale($order);
    app(IntegrationOutboxService::class)->recordPosCashSale($order); // replay
    for ($i = 0; $i < 5; $i++) {
        app(OutboxDispatcher::class)->dispatch(1);
    }
    expect(IntegrationOutboxEvent::where('event_type', 'pos.cash_sale')->count())->toBe(1)
        ->and(IntegrationOutboxEvent::first()->delivery_status)->toBe('DELIVERED');
});

test('POS cash timeout-after-commit → retry → DUPLICATE → DELIVERED, one Finance effect', function () {
    $committed = [];
    Http::fake(function ($r) use (&$committed) {
        $k = json_decode($r->body(), true)['idempotency_key'];
        if (! isset($committed[$k])) {
            $committed[$k] = true;
            throw new \Illuminate\Http\Client\ConnectionException('timeout-after-commit');
        }
        return Http::response(['result' => 'DUPLICATE'], 200);
    });
    app(IntegrationOutboxService::class)->recordPosCashSale(pos5CashOrder());
    app(OutboxDispatcher::class)->dispatch(1);
    expect(IntegrationOutboxEvent::first()->delivery_status)->toBe('FAILED_RETRYABLE');
    app(OutboxDispatcher::class)->dispatch(1);
    expect(IntegrationOutboxEvent::first()->delivery_status)->toBe('DELIVERED');
    expect(count($committed))->toBe(1);
});

test('POS cash Finance 422 mapping missing → FAILED_PERMANENT, source stays PAID', function () {
    Http::fake(fn () => Http::response(['code' => 'MAPPING_REQUIRED'], 422));
    $order = pos5CashOrder();
    app(IntegrationOutboxService::class)->recordPosCashSale($order);
    app(OutboxDispatcher::class)->dispatch(1);
    expect(IntegrationOutboxEvent::first()->delivery_status)->toBe('FAILED_PERMANENT')
        ->and($order->refresh()->payment_status)->toBe('PAID');
});

test('POS cash Finance unavailable → retryable, source stays PAID', function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
    $order = pos5CashOrder();
    app(IntegrationOutboxService::class)->recordPosCashSale($order);
    app(OutboxDispatcher::class)->dispatch(1);
    expect(IntegrationOutboxEvent::first()->delivery_status)->toBe('FAILED_RETRYABLE')
        ->and($order->refresh()->payment_status)->toBe('PAID');
});

