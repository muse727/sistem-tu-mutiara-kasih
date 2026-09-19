<?php

/**
 * TU-FINANCE-BATCH-05A — TU reconciliation transport/orchestration tests.
 *
 * Uses Http::fake() — no real network. Verifies snapshot→transport, HMAC
 * signing, invalid batch handling, transport/auth/unavailable classification,
 * repeat determinism, and that no TU accounting/payment data is mutated.
 */

use App\Models\SppSettlementBatch;
use App\Models\SppSettlementBatchItem;
use App\Models\StudentBill;
use App\Services\Integration\HmacIntegrationClient;
use App\Services\Integration\SppSettlementBatchReconciliationTransportService as RTS;
use Illuminate\Support\Facades\Http;

function rcBill(array $over = []): StudentBill
{
    $student = \App\Models\Student::factory()->create();
    return StudentBill::create(array_merge([
        'student_id' => $student->id, 'type' => 'SPP', 'name' => 'SPP',
        'amount' => 500000, 'status' => 'PAID', 'paid_at' => '2026-09-15 09:00:00',
        'payment_method' => 'CASH', 'bill_month' => 9, 'bill_year' => 2026,
    ], $over));
}

function rcBatch(array $bills): SppSettlementBatch
{
    config(['integration.spp_batch_enabled' => true, 'integration.spp_batch_activated_at' => null]);
    $svc = app(\App\Services\Integration\SppSettlementBatchService::class);
    foreach ($bills as $b) {
        $svc->recordEligibleSettlement($b);
    }
    return SppSettlementBatch::latest('id')->first();
}

beforeEach(function () {
    config(['integration.spp_batch_enabled' => true, 'integration.spp_batch_activated_at' => null]);
    config(['integration.finance.url' => 'https://finance.test/api/integration/tu/events']);
    config(['integration.finance.secret' => 'test-tu-secret']);
    config(['integration.finance.key_id' => 'tu-local']);
});

test('snapshot is transported with correct HMAC signature to reconciliation endpoint', function () {
    Http::fake(['*' => Http::response(['result' => 'OK', 'reconciliation' => ['status' => 'PENDING']], 200)]);
    $batch = rcBatch([rcBill()]);

    app(RTS::class)->reconcile($batch);

    Http::assertSent(function ($request) use ($batch) {
        $body = $request->body();
        $ts = $request->header('X-TU-Timestamp')[0];
        $path = '/api/integration/tu/reconciliation';
        $canonical = $ts . "\nPOST\n{$path}\n" . hash('sha256', $body);
        $expected = base64_encode(hash_hmac('sha256', $canonical, 'test-tu-secret', true));
        $json = json_decode($body, true);

        return $request->url() === 'https://finance.test/api/integration/tu/reconciliation'
            && $request->header('X-TU-Key-Id')[0] === 'tu-local'
            && $request->header('X-TU-Signature')[0] === $expected
            && ! str_contains($body, 'test-tu-secret')
            && $json['event_type'] === 'spp.reconciliation'
            && $json['source_system'] === 'TU'
            && $json['batch_id'] === (string) $batch->batch_id
            && $json['idempotency_key'] === (string) $batch->idempotency_key
            && is_array($json['snapshot']);
    });
});

test('reconciled result surfaces Finance reconciliation status verbatim', function () {
    Http::fake(['*' => Http::response([
        'result' => 'OK',
        'reconciliation' => ['status' => 'MATCHED', 'total_amount' => '500000.00', 'finance_transaction_id' => 'tx-1'],
    ], 200)]);
    $batch = rcBatch([rcBill()]);
    $r = app(RTS::class)->reconcile($batch);

    expect($r['result'])->toBe(RTS::RESULT_RECONCILED)
        ->and($r['status'])->toBe('MATCHED')
        ->and($r['reconciliation']['status'])->toBe('MATCHED')
        ->and($r['tu']['total_amount'])->toBe('500000.00');
});

test('mismatch is a reconciliation result, NOT a transport failure', function () {
    Http::fake(['*' => Http::response([
        'result' => 'OK',
        'reconciliation' => ['status' => 'AMOUNT_MISMATCH', 'reason' => 'differs'],
    ], 200)]);
    $batch = rcBatch([rcBill()]);
    $r = app(RTS::class)->reconcile($batch);

    expect($r['result'])->toBe(RTS::RESULT_RECONCILED)   // transport succeeded
        ->and($r['status'])->toBe('AMOUNT_MISMATCH');     // accounting mismatch surfaced
});

test('invalid batch id returns INVALID_BATCH without any HTTP call', function () {
    Http::fake();
    $r = app(RTS::class)->reconcileByBatchId('nonexistent-batch-id');
    expect($r['result'])->toBe(RTS::RESULT_INVALID_BATCH);
    Http::assertNothingSent();
});

test('authentication failure (401) classified as AUTH_FAILED', function () {
    Http::fake(['*' => Http::response(['result' => 'ERROR', 'code' => 'INVALID_SIGNATURE'], 401)]);
    $batch = rcBatch([rcBill()]);
    $r = app(RTS::class)->reconcile($batch);
    expect($r['result'])->toBe(RTS::RESULT_AUTH_FAILED)
        ->and($r['status'])->toBeNull();
});

test('Finance unavailable (timeout/network) classified as UNAVAILABLE', function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
    $batch = rcBatch([rcBill()]);
    $r = app(RTS::class)->reconcile($batch);
    expect($r['result'])->toBe(RTS::RESULT_UNAVAILABLE);
});

test('Finance 5xx classified as UNAVAILABLE', function () {
    Http::fake(['*' => Http::response('server error', 500)]);
    $batch = rcBatch([rcBill()]);
    $r = app(RTS::class)->reconcile($batch);
    expect($r['result'])->toBe(RTS::RESULT_UNAVAILABLE);
});

test('contract rejection (422) classified as CONTRACT_REJECTED', function () {
    Http::fake(['*' => Http::response(['result' => 'ERROR', 'code' => 'INVALID_ENVELOPE'], 422)]);
    $batch = rcBatch([rcBill()]);
    $r = app(RTS::class)->reconcile($batch);
    expect($r['result'])->toBe(RTS::RESULT_CONTRACT_REJECTED);
});

test('repeat reconciliation is deterministic and creates no TU mutation', function () {
    Http::fake(['*' => Http::response(['result' => 'OK', 'reconciliation' => ['status' => 'PENDING']], 200)]);
    $batch = rcBatch([rcBill(), rcBill()]);

    $beforeBatch = SppSettlementBatch::count();
    $beforeItems = SppSettlementBatchItem::count();
    $beforeBills = StudentBill::count();

    $r1 = app(RTS::class)->reconcile($batch);
    $r2 = app(RTS::class)->reconcile($batch->fresh());

    expect($r1['result'])->toBe($r2['result'])
        ->and($r1['status'])->toBe($r2['status'])
        ->and(SppSettlementBatch::count())->toBe($beforeBatch)
        ->and(SppSettlementBatchItem::count())->toBe($beforeItems)
        ->and(StudentBill::count())->toBe($beforeBills);
});

test('client derives endpoint root from configured finance url', function () {
    config(['integration.finance.url' => 'http://localhost:8000/api/integration/tu/events']);
    Http::fake(['*' => Http::response(['result' => 'OK', 'reconciliation' => []], 200)]);
    app(HmacIntegrationClient::class)->post('/api/integration/tu/reconciliation', ['x' => 1]);
    Http::assertSent(fn ($request) => $request->url() === 'http://localhost:8000/api/integration/tu/reconciliation');
});

test('client reports NOT_CONFIGURED when secret/url missing', function () {
    config(['integration.finance.secret' => null]);
    $r = app(HmacIntegrationClient::class)->post('/api/integration/tu/reconciliation', []);
    expect($r['outcome'])->toBe(HmacIntegrationClient::NOT_CONFIGURED);
});
