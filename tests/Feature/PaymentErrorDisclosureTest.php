<?php

/**
 * F-5 — Payment error information disclosure.
 *
 * createToken / createMultiToken must return a GENERIC user-safe error to the
 * browser, never the raw exception/provider/SQL detail. Detail stays in logs.
 *
 * Deterministic failure trigger (no live Midtrans call):
 *  - an empty MIDTRANS_SERVER_KEY makes Snap::getSnapToken() throw inside the try.
 */

use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

function f5Bill(): array {
    $student = Student::factory()->create();
    $bill    = StudentBill::factory()->create([
        'student_id' => $student->id,
        'status'     => 'UNPAID',
        'amount'     => 600000,
    ]);
    return [$student, $bill];
}

// ── Token-creation exception → generic error, HTTP 500, no detail leaked ────
test('token-creation exception returns a generic error without internal detail', function () {
    Config::set('services.midtrans.server_key', ''); // Snap throws on empty key
    [$student, $bill] = f5Bill();

    $resp = $this->actingAs($student, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay");

    $resp->assertStatus(500); // existing error status preserved
    expect($resp->json('error'))->toBe('Gagal membuat pembayaran online. Silakan coba lagi.');
    // no raw exception / SDK / config detail
    expect($resp->getContent())->not->toContain('server_key')
        ->and($resp->getContent())->not->toContain('Midtrans')
        ->and($resp->getContent())->not->toContain('Snap');
});

// ── Attack-string regression: response body never echoes internal diagnostics ─
test('generic error body contains no SQL/credential/path/config fragments', function () {
    Config::set('services.midtrans.server_key', '');
    [$student, $bill] = f5Bill();

    $resp = $this->actingAs($student, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay");

    $body = $resp->getContent();
    foreach (['SQLSTATE', 'password=', '/path/to', 'MIDTRANS_SERVER_KEY', 'internal provider', 'Trace', 'Stack'] as $needle) {
        expect($body)->not->toContain($needle);
    }
    expect($resp->json('error'))->toBe('Gagal membuat pembayaran online. Silakan coba lagi.');
});

// ── Server-side logging still captures the failure ──────────────────────────
test('detailed exception is logged server-side (not exposed)', function () {
    Config::set('services.midtrans.server_key', '');
    [$student, $bill] = f5Bill();

    Log::spy();
    $resp = $this->actingAs($student, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay");

    $resp->assertStatus(500);
    expect($resp->json('error'))->toBe('Gagal membuat pembayaran online. Silakan coba lagi.');
    Log::shouldHaveReceived('error')->with(\Mockery::pattern('/createToken failed/'), \Mockery::type('array'));
});

// ── Multi-token path also generic ───────────────────────────────────────────
test('createMultiToken exception also returns a generic error', function () {
    Config::set('services.midtrans.server_key', ''); // Snap throws inside multi try
    [$student, $bill] = f5Bill();

    $resp = $this->actingAs($student, 'siswa')->postJson('/siswa/payment/multi', [
        'bill_ids' => [$bill->id],
    ]);

    $resp->assertStatus(500);
    expect($resp->json('error'))->toBe('Gagal membuat pembayaran online. Silakan coba lagi.');
    expect($resp->getContent())->not->toContain('Midtrans')
        ->and($resp->getContent())->not->toContain('SQLSTATE');
});
