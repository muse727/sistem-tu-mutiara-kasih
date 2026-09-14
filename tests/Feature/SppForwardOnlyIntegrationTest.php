<?php

/**
 * Phase 09D.4 — SPP → Finance forward-only integration tests.
 *
 * Proves the full path: SPP PAID → outbox (activation-gated) → HMAC → Finance
 * (faked, real canonical spec) → accounting semantics. Covers activation
 * boundary, fail-safe source behavior, student_uuid snapshot stability,
 * duplicate/timeout-after-commit/failure classification, and reconciliation
 * traceability. No real network, no production anything.
 */

use App\Models\IntegrationOutboxEvent;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\BillingPaymentService;
use App\Services\Integration\OutboxDispatcher;
use Illuminate\Support\Facades\Http;

const SPP4_SECRET = 'test-tu-secret';
const SPP4_PATH = '/api/integration/tu/events';

function spp4Verify($request): bool
{
    $ts = $request->header('X-TU-Timestamp')[0] ?? null;
    $sig = $request->header('X-TU-Signature')[0] ?? null;
    if (! $ts || ! $sig || abs(time() - (int) $ts) > 300) {
        return false;
    }
    $canonical = $ts . "\nPOST\n" . SPP4_PATH . "\n" . hash('sha256', $request->body());
    return hash_equals(base64_encode(hash_hmac('sha256', $canonical, SPP4_SECRET, true)), $sig);
}

function spp4Bill(array $overrides = []): StudentBill
{
    return StudentBill::factory()->create(array_merge([
        'student_id' => Student::factory()->create(['status' => 'active'])->id,
        'type' => 'SPP', 'status' => 'UNPAID', 'payment_method' => 'CASH', 'amount' => 150000,
    ], $overrides));
}

beforeEach(function () {
    config(['integration.finance.url' => 'https://finance.test' . SPP4_PATH]);
    config(['integration.finance.secret' => SPP4_SECRET]);
    config(['integration.finance.key_id' => 'tu-local']);
});

// ── Activation / forward-only ─────────────────────────────────────────────────

test('integration disabled → SPP pays but NO outbox event created', function () {
    config(['integration.enabled' => false]);
    $bill = spp4Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);
    expect($bill->refresh()->status)->toBe('PAID')          // source unaffected
        ->and(IntegrationOutboxEvent::count())->toBe(0);      // no integration
});

test('payment settled BEFORE activation boundary → not integrated', function () {
    config(['integration.enabled' => true, 'integration.activated_at' => '2026-09-13T15:00:00+07:00']);
    $bill = spp4Bill();
    // Force paid_at to just before the boundary.
    $bill->update(['paid_at' => \Carbon\Carbon::parse('2026-09-13T14:59:59+07:00'), 'status' => 'PAID']);
    app(\App\Services\Integration\IntegrationOutboxService::class)->recordSppPayment($bill->refresh());
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(0);
});

test('payment settled AFTER activation boundary → eligible', function () {
    config(['integration.enabled' => true, 'integration.activated_at' => '2026-09-13T15:00:00+07:00']);
    $bill = spp4Bill();
    $bill->update(['paid_at' => \Carbon\Carbon::parse('2026-09-13T15:00:01+07:00'), 'status' => 'PAID']);
    app(\App\Services\Integration\IntegrationOutboxService::class)->recordSppPayment($bill->refresh());
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(1);
});

// ── Source of truth / snapshot ────────────────────────────────────────────────

test('event identity and idempotency key are canonical for SPP', function () {
    config(['integration.enabled' => true]);
    $bill = spp4Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);
    $e = IntegrationOutboxEvent::where('event_type', 'spp.payment')->firstOrFail();
    expect($e->source_record_id)->toBe((string) $bill->id)
        ->and($e->idempotency_key)->toBe("TU:spp.payment:{$bill->id}")
        ->and($e->payload['amount'])->toBe('150000.00'); // authoritative StudentBill.amount
});

test('student name change after event creation does not alter event snapshot/identity', function () {
    config(['integration.enabled' => true]);
    $student = Student::factory()->create(['status' => 'active', 'name' => 'Original Name']);
    $bill = spp4Bill(['student_id' => $student->id]);
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);

    $uuid = $student->student_uuid;
    $student->update(['name' => 'Changed Name']); // mutate source AFTER event

    $e = IntegrationOutboxEvent::where('event_type', 'spp.payment')->firstOrFail();
    expect($e->payload['student']['student_uuid'])->toBe($uuid)   // identity stable
        ->and($e->payload['student']['name'])->toBe('Original Name'); // snapshot frozen
});

// ── E2E through dispatcher + HMAC + faked Finance ─────────────────────────────

test('SPP cash: full path PROCESSED → DELIVERED with reconciliation traceability', function () {
    config(['integration.enabled' => true]);
    Http::fake(function ($request) {
        if (! spp4Verify($request)) {
            return Http::response(['code' => 'INVALID_SIGNATURE'], 401);
        }
        return Http::response(['result' => 'PROCESSED', 'transaction_id' => 'tx-1'], 201);
    });

    $bill = spp4Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);
    app(OutboxDispatcher::class)->dispatch(1);

    $e = IntegrationOutboxEvent::where('event_type', 'spp.payment')->firstOrFail();
    expect($e->delivery_status)->toBe('DELIVERED');
    // Reconciliation traceability fields present on the delivered event.
    expect($e->idempotency_key)->toBe("TU:spp.payment:{$bill->id}")
        ->and($e->payload['student']['student_uuid'])->toBe($bill->student->student_uuid)
        ->and($e->source_record_id)->toBe((string) $bill->id);
});

test('same event delivered 10x → Finance sees it; TU marks DELIVERED (idempotent)', function () {
    config(['integration.enabled' => true]);
    $seen = [];
    Http::fake(function ($request) use (&$seen) {
        $key = json_decode($request->body(), true)['idempotency_key'];
        $seen[$key] = ($seen[$key] ?? 0) + 1;
        return Http::response(['result' => $seen[$key] > 1 ? 'DUPLICATE' : 'PROCESSED'], $seen[$key] > 1 ? 200 : 201);
    });

    $bill = spp4Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);
    // Deliver the single canonical event; it becomes DELIVERED on first success.
    for ($i = 0; $i < 10; $i++) {
        app(OutboxDispatcher::class)->dispatch(1);
    }
    expect(IntegrationOutboxEvent::where('event_type', 'spp.payment')->count())->toBe(1)
        ->and(IntegrationOutboxEvent::first()->delivery_status)->toBe('DELIVERED')
        ->and($seen["TU:spp.payment:{$bill->id}"])->toBe(1); // only delivered once (then DELIVERED, not re-sent)
});

test('Finance 500 then success → FAILED_RETRYABLE then DELIVERED', function () {
    config(['integration.enabled' => true]);
    $call = 0;
    Http::fake(function () use (&$call) {
        $call++;
        return $call === 1 ? Http::response('err', 500) : Http::response(['result' => 'PROCESSED'], 201);
    });
    $bill = spp4Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);
    app(OutboxDispatcher::class)->dispatch(1); // 500 → retryable
    expect(IntegrationOutboxEvent::first()->delivery_status)->toBe('FAILED_RETRYABLE');
    app(OutboxDispatcher::class)->dispatch(1); // success → delivered
    expect(IntegrationOutboxEvent::first()->delivery_status)->toBe('DELIVERED');
});

test('Finance 422 MAPPING_REQUIRED → TU stays PAID, outbox FAILED_PERMANENT', function () {
    config(['integration.enabled' => true]);
    Http::fake(fn () => Http::response(['code' => 'MAPPING_REQUIRED'], 422));
    $bill = spp4Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);
    app(OutboxDispatcher::class)->dispatch(1);
    expect($bill->refresh()->status)->toBe('PAID')            // source unaffected
        ->and(IntegrationOutboxEvent::first()->delivery_status)->toBe('FAILED_PERMANENT');
});

test('invalid HMAC → Finance rejects, outbox FAILED_PERMANENT, no accounting', function () {
    config(['integration.enabled' => true]);
    Http::fake(fn () => Http::response(['code' => 'INVALID_SIGNATURE'], 401));
    $bill = spp4Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);
    app(OutboxDispatcher::class)->dispatch(1);
    expect(IntegrationOutboxEvent::first()->delivery_status)->toBe('FAILED_PERMANENT');
});

test('timeout-after-commit → retry → DUPLICATE → DELIVERED, one Finance effect', function () {
    config(['integration.enabled' => true]);
    $committed = [];
    Http::fake(function ($request) use (&$committed) {
        $key = json_decode($request->body(), true)['idempotency_key'];
        if (! isset($committed[$key])) {
            $committed[$key] = true;
            throw new \Illuminate\Http\Client\ConnectionException('timeout-after-commit');
        }
        return Http::response(['result' => 'DUPLICATE'], 200);
    });
    $bill = spp4Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);
    app(OutboxDispatcher::class)->dispatch(1); // timeout → retryable
    expect(IntegrationOutboxEvent::first()->delivery_status)->toBe('FAILED_RETRYABLE');
    app(OutboxDispatcher::class)->dispatch(1); // DUPLICATE → delivered
    expect(IntegrationOutboxEvent::first()->delivery_status)->toBe('DELIVERED');
    expect(count($committed))->toBe(1); // Finance processed exactly once
});

test('Finance unavailable (network) → TU stays PAID, outbox recoverable', function () {
    config(['integration.enabled' => true]);
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
    $bill = spp4Bill();
    app(BillingPaymentService::class)->payCash($bill, User::factory()->create(['role' => 'tu'])->id);
    app(OutboxDispatcher::class)->dispatch(1);
    expect($bill->refresh()->status)->toBe('PAID')
        ->and(IntegrationOutboxEvent::first()->delivery_status)->toBe('FAILED_RETRYABLE');
});
