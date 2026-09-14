<?php

/**
 * Phase E.1 — Flip Accept Payment V2 payment-creation feature tests.
 *
 * Endpoint: POST /siswa/tagihan/{bill}/pay-flip (siswa-authenticated).
 * Verified contract: create returns link_id + link_url with status ACTIVE.
 *
 * Covers:
 *   A. Successful creation → PaymentAttempt (provider FLIP) + provider-neutral
 *      response with payment_url, string payment_reference; bill stays UNPAID.
 *   B. Idempotency: existing open attempt → same attempt reused, ONE provider call.
 *   C. Provider failure → safe 502, no attempt, no false PAID.
 *   D. 19-digit link_id stored as exact string.
 *   E. Unauthorized (another student's bill) → 403.
 *   F. Already-PAID bill → 400, no provider call.
 *   G. Secret never present in the response payload.
 *   H. Amount is the authoritative bill amount, never frontend input.
 *
 * Http::fake intercepts all provider calls — no real network access.
 */

use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.flip.api_url', 'https://bigflip.id/big_sandbox_api');
    Config::set('services.flip.secret_key', 'flip_test_secret');
    Config::set('services.flip.callback_token', 'flip_test_token');
    // No global Http::fake — each test fakes precisely what it needs (Laravel
    // resolves stacked fakes first-match-wins, so a global fake would shadow).
});

/** Fake a successful create-bill response with a given link id (verified shape). */
function fakeFlipCreate(string $linkId = '2502091430251230005', string $url = 'flip.id/pwf-sandbox/$x/#payment'): void
{
    Http::fake([
        'bigflip.id/*' => Http::response([
            'link_id'  => $linkId,
            'link_url' => $url,
            'status'   => 'ACTIVE',
        ], 200),
    ]);
}

function mkStudent(): Student
{
    return Student::factory()->create();
}

function mkBill(Student $student, int $amount = 150000, string $status = 'UNPAID'): StudentBill
{
    return StudentBill::factory()->create([
        'student_id' => $student->id,
        'type'       => 'SPP',
        'name'       => 'SPP Test',
        'amount'     => $amount,
        'status'     => $status,
    ]);
}

// ── A. Successful creation ──────────────────────────────────────────────────

test('creates a Flip bill and returns provider-neutral payload, bill stays UNPAID', function () {
    fakeFlipCreate();
    $student = mkStudent();
    $bill    = mkBill($student, 150000);

    $res = $this->actingAs($student, 'siswa')
        ->postJson("/siswa/tagihan/{$bill->id}/pay-flip");

    $res->assertStatus(200)
        ->assertJsonStructure(['provider', 'payment_reference', 'payment_url', 'status'])
        ->assertJson([
            'provider'          => 'flip',
            'payment_reference' => '2502091430251230005',
            'payment_url'       => 'flip.id/pwf-sandbox/$x/#payment',
            'status'            => 'ACTIVE',
        ]);

    // Bill remains UNPAID (creation never settles).
    expect($bill->fresh()->status)->toBe('UNPAID');

    $attempt = PaymentAttempt::where('student_bill_id', $bill->id)->sole();
    expect($attempt->provider)->toBe('FLIP')
        ->and($attempt->provider_reference)->toBe('2502091430251230005')
        ->and($attempt->provider_reference)->toBeString()
        ->and($attempt->payment_method)->toBe('ONLINE')
        ->and($attempt->payment_url)->toBe('flip.id/pwf-sandbox/$x/#payment')
        ->and($attempt->status)->toBe(PaymentAttempt::STATUS_PENDING)
        ->and((int) $attempt->gross_amount)->toBe(150000);
});

// ── B. Idempotency — one open attempt per bill ──────────────────────────────

test('repeated creation reuses the single open attempt (one provider call)', function () {
    fakeFlipCreate();
    $student = mkStudent();
    $bill    = mkBill($student);

    $this->actingAs($student, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay-flip")->assertStatus(200);
    $this->actingAs($student, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay-flip")->assertStatus(200);
    $this->actingAs($student, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay-flip")->assertStatus(200);

    expect(PaymentAttempt::where('student_bill_id', $bill->id)->count())->toBe(1);
    Http::assertSentCount(1);
});

// ── C. Provider failure ─────────────────────────────────────────────────────

test('provider failure returns 502 with no attempt and no false PAID', function () {
    Http::fake(['bigflip.id/*' => Http::response(['code' => 'SERVER_ERROR'], 500)]);

    $student = mkStudent();
    $bill    = mkBill($student);

    $this->actingAs($student, 'siswa')
        ->postJson("/siswa/tagihan/{$bill->id}/pay-flip")
        ->assertStatus(502);

    expect(PaymentAttempt::where('student_bill_id', $bill->id)->count())->toBe(0);
    expect($bill->fresh()->status)->toBe('UNPAID');
});

// ── D. 19-digit reference stored as string ──────────────────────────────────

test('stores a 19-digit link_id as an exact string', function () {
    fakeFlipCreate('2502091430251239999', 'flip.id/x/#p');

    $student = mkStudent();
    $bill    = mkBill($student);

    $this->actingAs($student, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay-flip")->assertStatus(200);

    $ref = PaymentAttempt::where('student_bill_id', $bill->id)->sole()->provider_reference;
    expect($ref)->toBe('2502091430251239999')->and(strlen($ref))->toBe(19);
});

// ── E/F. Authorization & state guards ───────────────────────────────────────

test('cannot create a Flip bill for another student', function () {
    $a = mkStudent();
    $b = mkStudent();
    $bill = mkBill($b);

    $this->actingAs($a, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay-flip")->assertStatus(403);
    expect(PaymentAttempt::count())->toBe(0);
    Http::assertNothingSent();
});

test('cannot create a Flip bill for an already-PAID bill', function () {
    $student = mkStudent();
    $bill    = mkBill($student, 150000, 'PAID');

    $this->actingAs($student, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay-flip")->assertStatus(400);
    Http::assertNothingSent();
});

// ── G. Secret never exposed ─────────────────────────────────────────────────

test('response never contains the Flip secret or auth header', function () {
    fakeFlipCreate();
    $student = mkStudent();
    $bill    = mkBill($student);

    $res = $this->actingAs($student, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay-flip");
    $body = $res->getContent();

    expect($body)->not->toContain('flip_test_secret')
        ->and($body)->not->toContain('Authorization')
        ->and($body)->not->toContain('Basic ');
});

// ── H. Authoritative amount ─────────────────────────────────────────────────

test('uses the bill amount, not any frontend-supplied amount', function () {
    fakeFlipCreate();
    $student = mkStudent();
    $bill    = mkBill($student, 275000);

    $this->actingAs($student, 'siswa')
        ->postJson("/siswa/tagihan/{$bill->id}/pay-flip", ['amount' => 1])
        ->assertStatus(200);

    Http::assertSent(fn ($r) => $r['amount'] == 275000);
    expect((int) PaymentAttempt::where('student_bill_id', $bill->id)->sole()->gross_amount)->toBe(275000);
});
