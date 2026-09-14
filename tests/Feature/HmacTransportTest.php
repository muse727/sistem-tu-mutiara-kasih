<?php

/**
 * Phase 09D.3 — TU HMAC transport tests. Uses Http::fake() — no real network.
 *
 * Verifies signing (headers, body hash, canonical string), response
 * classification (2xx/4xx/5xx/timeout/429), and payload stability across retry.
 */

use App\Models\IntegrationOutboxEvent;
use App\Models\PosOrder;
use App\Models\User;
use App\Services\Integration\HmacIntegrationTransport;
use App\Services\Integration\IntegrationOutboxService;
use App\Services\Integration\TransportResult;
use Illuminate\Support\Facades\Http;

function hmacOrder(string $code = 'TRX-T-1'): PosOrder
{
    return PosOrder::create([
        'user_id' => User::factory()->create(['role' => 'tu'])->id, 'student_id' => null,
        'transaction_code' => $code, 'total_amount' => 15000, 'payment_amount' => 20000,
        'change_amount' => 5000, 'payment_status' => 'PAID', 'redemption_status' => 'COMPLETED',
        'qr_token' => (string) \Illuminate\Support\Str::uuid(),
    ]);
}

function hmacEvent(PosOrder $order): IntegrationOutboxEvent
{
    return app(IntegrationOutboxService::class)->recordPosCashSale($order);
}

beforeEach(function () {
    config(['integration.enabled' => true, 'integration.activated_at' => null]); // Phase 09D.5: enabled, no boundary
    config(['integration.finance.url' => 'https://finance.test/api/integration/tu/events']);
    config(['integration.finance.secret' => 'test-tu-secret']);
    config(['integration.finance.key_id' => 'tu-local']);
});

test('signed request has correct headers and body hash', function () {
    Http::fake(['*' => Http::response(['result' => 'PROCESSED'], 201)]);
    $event = hmacEvent(hmacOrder());
    app(HmacIntegrationTransport::class)->deliver($event);

    Http::assertSent(function ($request) {
        $body = $request->body();
        $ts = $request->header('X-TU-Timestamp')[0];
        $path = '/api/integration/tu/events';
        $canonical = $ts . "\nPOST\n{$path}\n" . hash('sha256', $body);
        $expected = base64_encode(hash_hmac('sha256', $canonical, 'test-tu-secret', true));

        return $request->header('X-TU-Key-Id')[0] === 'tu-local'
            && $request->header('X-TU-Signature')[0] === $expected
            && ! str_contains($body, 'test-tu-secret'); // secret never in body
    });
});

test('2xx PROCESSED maps to DELIVERED', function () {
    Http::fake(['*' => Http::response(['result' => 'PROCESSED'], 201)]);
    $r = app(HmacIntegrationTransport::class)->deliver(hmacEvent(hmacOrder('TRX-A1')));
    expect($r->outcome)->toBe(TransportResult::DELIVERED);
});

test('200 DUPLICATE maps to DELIVERED', function () {
    Http::fake(['*' => Http::response(['result' => 'DUPLICATE'], 200)]);
    $r = app(HmacIntegrationTransport::class)->deliver(hmacEvent(hmacOrder('TRX-A2')));
    expect($r->outcome)->toBe(TransportResult::DELIVERED);
});

test('422 maps to FAILED_PERMANENT', function () {
    Http::fake(['*' => Http::response(['code' => 'INVALID_AMOUNT'], 422)]);
    $r = app(HmacIntegrationTransport::class)->deliver(hmacEvent(hmacOrder('TRX-A3')));
    expect($r->outcome)->toBe(TransportResult::PERMANENT);
});

test('401 maps to FAILED_PERMANENT', function () {
    Http::fake(['*' => Http::response(['code' => 'INVALID_SIGNATURE'], 401)]);
    $r = app(HmacIntegrationTransport::class)->deliver(hmacEvent(hmacOrder('TRX-A4')));
    expect($r->outcome)->toBe(TransportResult::PERMANENT);
});

test('500 maps to FAILED_RETRYABLE', function () {
    Http::fake(['*' => Http::response('err', 500)]);
    $r = app(HmacIntegrationTransport::class)->deliver(hmacEvent(hmacOrder('TRX-A5')));
    expect($r->outcome)->toBe(TransportResult::RETRYABLE);
});

test('429 maps to FAILED_RETRYABLE', function () {
    Http::fake(['*' => Http::response('', 429)]);
    $r = app(HmacIntegrationTransport::class)->deliver(hmacEvent(hmacOrder('TRX-A6')));
    expect($r->outcome)->toBe(TransportResult::RETRYABLE);
});

test('408 maps to FAILED_RETRYABLE', function () {
    Http::fake(['*' => Http::response('', 408)]);
    $r = app(HmacIntegrationTransport::class)->deliver(hmacEvent(hmacOrder('TRX-A7')));
    expect($r->outcome)->toBe(TransportResult::RETRYABLE);
});

test('network exception maps to FAILED_RETRYABLE', function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
    $r = app(HmacIntegrationTransport::class)->deliver(hmacEvent(hmacOrder('TRX-A8')));
    expect($r->outcome)->toBe(TransportResult::RETRYABLE);
});

test('event payload unchanged across retry', function () {
    Http::fake(['*' => Http::response('err', 500)]);
    $event = hmacEvent(hmacOrder('TRX-A9'));
    $payloadBefore = $event->payload;
    $t = app(HmacIntegrationTransport::class);
    $t->deliver($event);
    $t->deliver($event->refresh());
    expect($event->refresh()->payload)->toEqual($payloadBefore);
});

test('unconfigured transport is retryable and never calls network', function () {
    config(['integration.finance.url' => null]);
    Http::fake();
    $r = app(HmacIntegrationTransport::class)->deliver(hmacEvent(hmacOrder('TRX-A10')));
    expect($r->outcome)->toBe(TransportResult::RETRYABLE);
    Http::assertNothingSent();
});
