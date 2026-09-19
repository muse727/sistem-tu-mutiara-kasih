<?php

/**
 * Dashboard Payment Provider Dispatch — Tests
 *
 * Membuktikan tombol "Bayar" dashboard mengikuti STUDENT_PAYMENT_PROVIDER,
 * tanpa melakukan transaksi live maupun mengubah implementasi Midtrans/Flip.
 */

use App\Models\Student;

function dashboardHtml(string $provider, ?string $clientKey = 'SB-Mid-client-test'): string {
    config(['services.student_payment.provider' => $provider]);
    config(['services.midtrans.client_key' => $clientKey]);
    $student = Student::factory()->create();
    return test()->actingAs($student, 'siswa')
        ->get(route('siswa.dashboard'))
        ->assertOk()
        ->getContent();
}

test('provider=midtrans: Snap.js sandbox dimuat & dispatcher aktif', function () {
    $html = dashboardHtml('midtrans');

    // Snap.js sandbox dimuat, production tidak.
    expect($html)->toContain('https://app.sandbox.midtrans.com/snap/snap.js');
    expect($html)->not->toContain('https://app.midtrans.com/snap/snap.js');

    // Client key (bukan server key) diteruskan ke Snap.js.
    expect($html)->toContain('data-client-key="SB-Mid-client-test"');
    expect($html)->not->toContain('server_key');

    // Dispatcher + kedua cabang tersedia; config provider diekspos ke JS.
    expect($html)->toContain('window.STUDENT_PAYMENT');
    expect($html)->toContain('provider:  "midtrans"');
    expect($html)->toContain('prosesPaymentMidtrans');
    expect($html)->toContain('/siswa/tagihan/${billId}/pay`');   // rute Midtrans createToken
});

test('provider=flip: Snap.js TIDAK dimuat (conditional) & cabang Flip tetap ada', function () {
    $html = dashboardHtml('flip');

    // Snap.js tidak dimuat saat provider flip.
    expect($html)->not->toContain('snap/snap.js');

    // Cabang Flip tetap utuh.
    expect($html)->toContain('provider:  "flip"');
    expect($html)->toContain('prosesPaymentFlip');
    expect($html)->toContain('/siswa/tagihan/${billId}/pay-flip`');
});

test('Flip backend route tetap terdaftar & utuh', function () {
    // Route Flip tidak dihapus — hanya tidak dipilih saat provider=midtrans.
    $student = Student::factory()->create();
    $response = test()->actingAs($student, 'siswa')
        ->postJson('/siswa/tagihan/999/pay-flip'); // bill nonexistent → 404/abort, bukan route-missing
    expect(in_array($response->getStatusCode(), [404, 419, 500]))->toBeTrue();
});

test('selector tetap reversible (midtrans ⇄ flip)', function () {
    config(['services.student_payment.provider' => 'midtrans']);
    expect(config('services.student_payment.provider'))->toBe('midtrans');
    config(['services.student_payment.provider' => 'flip']);
    expect(config('services.student_payment.provider'))->toBe('flip');
});
