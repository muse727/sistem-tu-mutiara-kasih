<?php

/**
 * Payment Provider Selector — Foundation Tests
 *
 * Selector ini HANYA deklarasi konfigurasi (reversible via env). Ia tidak
 * mengubah controller, UI, settlement, maupun integrasi Finance.
 */

test('student payment provider selector is registered and defaults to midtrans', function () {
    expect(config('services.student_payment.provider'))->toBe('midtrans');
});

test('selector exposes the supported providers allowlist', function () {
    expect(config('services.student_payment.supported'))
        ->toBeArray()
        ->toContain('midtrans')
        ->toContain('flip');
});

test('default provider is within the supported allowlist', function () {
    $provider  = config('services.student_payment.provider');
    $supported = config('services.student_payment.supported');

    expect($supported)->toContain($provider);
});

test('selector is reversible via env (no code change needed)', function () {
    // Simulasikan pergantian env lalu config:clear — selector mengikuti env.
    config(['services.student_payment.provider' => 'flip']);
    expect(config('services.student_payment.provider'))->toBe('flip');

    // Kembalikan ke midtrans (reversible).
    config(['services.student_payment.provider' => 'midtrans']);
    expect(config('services.student_payment.provider'))->toBe('midtrans');
});

test('adding the selector does not disturb existing provider config', function () {
    // Midtrans & Flip config sections tetap utuh.
    expect(config('services.midtrans'))->toBeArray()
        ->toHaveKeys(['server_key', 'client_key', 'is_production']);
    expect(config('services.flip'))->toBeArray()
        ->toHaveKeys(['api_url', 'secret_key', 'callback_token']);
});
