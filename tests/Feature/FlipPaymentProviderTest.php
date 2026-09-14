<?php

/**
 * Phase E.1 — FlipPaymentProvider unit tests.
 *
 * Uses Http::fake() so NO real network calls are made. Asserts the EMPIRICALLY
 * VERIFIED sandbox V2 contract (docs/PHASE-E0.2-FLIP-V2-CONTRACT.md):
 *   - POST /v2/pwf/bill with HTTP Basic (secret as username, empty password)
 *   - create response uses link_id + link_url, status ACTIVE
 *   - link_id kept as STRING (never cast)
 *   - status inquiry is GET /v2/pwf/bill (a LIST) matched locally by link_id
 *   - constant-time callback token verification
 *   - status mapping (SUCCESSFUL→success, FAILED/…→failed, ACTIVE/PENDING→pending)
 */

use App\Services\Payments\FlipPaymentProvider;
use App\Services\Payments\ProviderException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.flip.api_url', 'https://bigflip.id/big_sandbox_api');
    Config::set('services.flip.secret_key', 'test_secret_key_abc');
    Config::set('services.flip.callback_token', 'test_callback_token_xyz');
});

function flipProvider(): FlipPaymentProvider
{
    return new FlipPaymentProvider();
}

// ── Create Bill ─────────────────────────────────────────────────────────────

test('createBill posts to V2 endpoint with HTTP Basic auth and form body', function () {
    Http::fake([
        'bigflip.id/*' => Http::response([
            'link_id'  => 356056,
            'link_url' => 'flip.id/pwf-sandbox/$x/#abc',
            'status'   => 'ACTIVE',
        ], 200),
    ]);

    $bill = flipProvider()->createBill(150000, 'SPP Januari', [
        'type' => 'SINGLE', 'step' => 1, 'redirect_url' => 'https://tu.test/ok',
    ]);

    expect($bill->reference)->toBe('356056')
        ->and($bill->reference)->toBeString()
        ->and($bill->paymentUrl)->toBe('flip.id/pwf-sandbox/$x/#abc')
        ->and($bill->status)->toBe('ACTIVE');

    Http::assertSent(function ($request) {
        $expectedAuth = 'Basic ' . base64_encode('test_secret_key_abc:');
        return $request->url() === 'https://bigflip.id/big_sandbox_api/v2/pwf/bill'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', $expectedAuth)
            && $request['amount'] == 150000
            && $request['title'] === 'SPP Januari'
            && $request['type'] === 'SINGLE'
            && $request['step'] == 1;
    });
});

test('createBill defaults step to 1 and omits sender fields when absent', function () {
    Http::fake(['bigflip.id/*' => Http::response(['link_id' => 1, 'link_url' => 'u', 'status' => 'ACTIVE'], 200)]);

    flipProvider()->createBill(10000, 'T');

    Http::assertSent(fn ($r) => ! isset($r['sender_name']) && ! isset($r['sender_email']) && $r['step'] == 1);
});

test('createBill keeps a large link_id as an exact string', function () {
    Http::fake([
        'bigflip.id/*' => Http::response([
            'link_id'  => 2502091430251230005, // numeric in JSON
            'link_url' => 'flip.id/x/#y',
            'status'   => 'ACTIVE',
        ], 200),
    ]);

    $bill = flipProvider()->createBill(10000, 'T');
    expect($bill->reference)->toBe('2502091430251230005')
        ->and(strlen($bill->reference))->toBe(19);
});

test('createBill throws ProviderException on auth failure (401)', function () {
    Http::fake(['bigflip.id/*' => Http::response(['errors' => [['code' => 401, 'message' => 'invalid credentials']]], 401)]);
    flipProvider()->createBill(10000, 'T');
})->throws(ProviderException::class);

test('createBill throws ProviderException on VALIDATION_ERROR array', function () {
    Http::fake(['bigflip.id/*' => Http::response([
        'code' => 'VALIDATION_ERROR',
        'errors' => [['attribute' => 'sender_name', 'code' => 1070, 'message' => 'Param sender_name is required']],
    ], 422)]);
    flipProvider()->createBill(10000, 'T');
})->throws(ProviderException::class);

test('createBill throws when reference or url missing', function () {
    Http::fake(['bigflip.id/*' => Http::response(['status' => 'ACTIVE'], 200)]);
    flipProvider()->createBill(10000, 'T');
})->throws(ProviderException::class);

test('createBill rejects non-positive amount', function () {
    flipProvider()->createBill(0, 'T');
})->throws(ProviderException::class);

// ── Status inquiry (single-bill, verified) ──────────────────────────────────

test('queryStatus GETs /v2/pwf/{link_id}/bill and maps SUCCESSFUL', function () {
    Http::fake([
        'bigflip.id/*' => Http::response([
            'link_id' => 356057,
            'status'  => 'SUCCESSFUL',
            'amount'  => 150000,
        ], 200),
    ]);

    $s = flipProvider()->queryStatus('356057');
    expect($s->providerStatus)->toBe('SUCCESSFUL')
        ->and($s->canonical)->toBe('success')
        ->and($s->amountIdr)->toBe(150000)
        ->and($s->reference)->toBe('356057');

    Http::assertSent(fn ($r) =>
        $r->method() === 'GET'
        && $r->url() === 'https://bigflip.id/big_sandbox_api/v2/pwf/356057/bill'
        && $r->hasHeader('Authorization', 'Basic ' . base64_encode('test_secret_key_abc:'))
    );
});

test('queryStatus throws NOT_FOUND on 404', function () {
    Http::fake(['bigflip.id/*' => Http::response(['name' => 'Not Found'], 404)]);
    flipProvider()->queryStatus('999999');
})->throws(ProviderException::class);

test('queryStatus throws on http failure', function () {
    Http::fake(['bigflip.id/*' => Http::response([], 500)]);
    flipProvider()->queryStatus('356056');
})->throws(ProviderException::class);

// ── Callback token (constant-time) ──────────────────────────────────────────

test('verifyCallbackToken accepts exact match and rejects mismatch', function () {
    $p = flipProvider();
    expect($p->verifyCallbackToken('test_callback_token_xyz'))->toBeTrue()
        ->and($p->verifyCallbackToken('wrong'))->toBeFalse()
        ->and($p->verifyCallbackToken(''))->toBeFalse()
        ->and($p->verifyCallbackToken(null))->toBeFalse();
});

test('verifyCallbackToken fails closed when expected token not configured', function () {
    Config::set('services.flip.callback_token', '');
    expect(flipProvider()->verifyCallbackToken('anything'))->toBeFalse();
});

// ── Status mapping ──────────────────────────────────────────────────────────

test('mapStatus maps Flip statuses to canonical values', function () {
    $p = flipProvider();
    expect($p->mapStatus('SUCCESSFUL'))->toBe('success')
        ->and($p->mapStatus('successful'))->toBe('success')
        ->and($p->mapStatus('ACTIVE'))->toBe('pending')
        ->and($p->mapStatus('PENDING'))->toBe('pending')
        ->and($p->mapStatus('FAILED'))->toBe('failed')
        ->and($p->mapStatus('CANCELLED'))->toBe('failed')
        ->and($p->mapStatus('EXPIRED'))->toBe('failed')
        ->and($p->mapStatus('SOMETHING_ELSE'))->toBe('pending');
});
