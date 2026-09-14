<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Turnstile testability regression tests.
 *
 * Verifies the Cloudflare Turnstile boundary used by the admin login
 * (LoginRequest) and the siswa portal login (AuthSiswaController):
 *
 *   1. valid Turnstile verification   → login succeeds
 *   2. invalid Turnstile verification → login rejected
 *   3. missing token                  → login rejected (validation)
 *   4. valid token                    → login permitted
 *
 * Tests fake the Http facade for the siteverify endpoint only — no real
 * network call to Cloudflare is ever made, and production verification is
 * left fully enabled.
 */

// ── 1 & 4. valid verification permits login ─────────────────────────────────
test('valid turnstile verification permits admin login', function () {
    fakeTurnstileSuccess();
    $user = User::factory()->create();

    $this->post('/login', [
        'email'                 => $user->email,
        'password'              => 'password',
        'cf-turnstile-response' => 'valid-token',
    ])->assertRedirect();

    $this->assertAuthenticated();

    // The HTTP call was made to Cloudflare siteverify (faked).
    Http::assertSent(fn ($request) =>
        str_contains($request->url(), 'challenges.cloudflare.com/turnstile')
    );
});

// ── 2. invalid verification rejects login ───────────────────────────────────
test('invalid turnstile verification rejects admin login', function () {
    fakeTurnstileFailure();
    $user = User::factory()->create();

    $this->from('/login')->post('/login', [
        'email'                 => $user->email,
        'password'              => 'password',
        'cf-turnstile-response' => 'forged-token',
    ])->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
});

// ── 3. missing token rejects login (validation) ─────────────────────────────
test('missing turnstile token rejects admin login', function () {
    $user = User::factory()->create();

    $this->from('/login')->post('/login', [
        'email'    => $user->email,
        'password' => 'password',
        // no cf-turnstile-response field
    ])->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
});

// ── siswa portal login honours the same boundary ────────────────────────────
test('invalid turnstile verification rejects siswa login', function () {
    fakeTurnstileFailure();
    $student = \App\Models\Student::factory()->create([
        'nis'             => '123456',
        'birth_date'      => '2008-01-01',
        'custom_password' => \Illuminate\Support\Facades\Hash::make('010108'),
        'status'          => 'active',
    ]);

    $this->from('/siswa/login')->post('/siswa/login', [
        'nis'                   => $student->nis,
        'password'              => '010108',
        'cf-turnstile-response' => 'forged-token',
    ])->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest('siswa');
});

test('valid turnstile verification permits siswa login', function () {
    fakeTurnstileSuccess();
    $student = \App\Models\Student::factory()->create([
        'nis'             => '654321',
        'birth_date'      => '2008-01-01',
        'custom_password' => \Illuminate\Support\Facades\Hash::make('010108'),
        'status'          => 'active',
    ]);

    $this->post('/siswa/login', [
        'nis'                   => $student->nis,
        'password'              => '010108',
        'cf-turnstile-response' => 'valid-token',
    ])->assertRedirect();

    $this->assertAuthenticated('siswa');
});
