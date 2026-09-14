<?php

/**
 * Phase E.1 — Flip Accept Payment V2 callback feature tests.
 *
 * Endpoint: POST /siswa/payment/flip-callback (form-urlencoded: data, token).
 *
 * Covers the full decision matrix:
 *   A. valid token + SUCCESSFUL + correct amount → bill PAID + attempt settled
 *   B. invalid token → no settlement
 *   C. missing token → no settlement
 *   D. malformed data JSON → no settlement
 *   E. unknown bill_link_id → no settlement
 *   F. amount mismatch → no settlement (no PAID, no outbox)
 *   G. non-success status (FAILED) → no PAID; attempt closed
 *   H. PENDING status → no PAID; attempt left open
 *   I. duplicate callback (2x/5x/10x) → exactly one settlement
 *   J. callback after bill already PAID → no overwrite
 *   K. 19-digit bill_link_id resolution as string
 *
 * Outbox is gated by integration.enabled; here we enable it to assert the
 * event is created exactly once on success and never on rejection.
 */

use App\Models\IntegrationOutboxEvent;
use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('services.flip.callback_token', 'flip_test_token_123');
    Config::set('integration.enabled', true);
    Config::set('integration.activated_at', null);
});

function flipBill(int $amount = 150000): StudentBill
{
    return StudentBill::factory()->create([
        'student_id' => Student::factory(),
        'type'       => 'SPP',
        'amount'     => $amount,
        'status'     => 'UNPAID',
    ]);
}

function flipAttempt(StudentBill $bill, string $linkId): PaymentAttempt
{
    return PaymentAttempt::factory()->flip($bill, $linkId)->create([
        'gross_amount' => $bill->amount,
    ]);
}

function flipCallbackPayload(PaymentAttempt $attempt, int $amount, string $status): array
{
    return [
        // Verified sandbox field name is `link_id` (kept as a string).
        'link_id'     => (string) $attempt->provider_reference,
        'status'      => $status,
        'amount'      => $amount,
        'sender_bank' => 'bca',
        'sender_name' => 'Test Sender',
    ];
}

/**
 * POST a Flip callback as application/x-www-form-urlencoded (Flip's real
 * content type), with `data` as a JSON string and `token` as the dashboard
 * validation token.
 */
function postFlipCallback($tc, array $data, ?string $token = 'flip_test_token_123')
{
    $params = ['data' => json_encode($data)];
    if ($token !== null) {
        $params['token'] = $token;
    }
    return $tc->call('POST', '/siswa/payment/flip-callback', $params, [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
    ]);
}

// ── A. Happy path ───────────────────────────────────────────────────────────

test('valid SUCCESSFUL callback settles bill PAID and attempt', function () {
    $bill    = flipBill(150000);
    $attempt = flipAttempt($bill, '2502091430251230005');

    postFlipCallback($this, flipCallbackPayload($attempt, 150000, 'SUCCESSFUL'))
        ->assertStatus(200);

    $bill->refresh();
    $attempt->refresh();

    expect($bill->status)->toBe('PAID')
        ->and($bill->payment_method)->toBe('ONLINE')
        ->and($bill->paid_at)->not->toBeNull()
        ->and($attempt->status)->toBe(PaymentAttempt::STATUS_SETTLEMENT)
        ->and($attempt->provider_status)->toBe('SUCCESSFUL')
        ->and($attempt->settled_at)->not->toBeNull();

    // Exactly one outbox event, with provider = flip and the string reference.
    $events = IntegrationOutboxEvent::where('source_record_id', (string) $bill->id)
        ->where('event_type', 'spp.payment')->get();
    expect($events)->toHaveCount(1);
    $payload = $events->first()->payload;
    expect($payload['provider'])->toBe('flip')
        ->and($payload['provider_reference'])->toBe('2502091430251230005')
        ->and($payload['provider_reference'])->toBeString()
        ->and($events->first()->idempotency_key)->toBe('TU:spp.payment:' . $bill->id);
});

// ── B/C. Token validation ───────────────────────────────────────────────────

test('invalid token is rejected without settlement', function () {
    $bill    = flipBill();
    $attempt = flipAttempt($bill, '2502091430251230005');

    postFlipCallback($this, flipCallbackPayload($attempt, 150000, 'SUCCESSFUL'), 'WRONG_TOKEN')
        ->assertStatus(200);

    expect($bill->fresh()->status)->toBe('UNPAID');
    expect(IntegrationOutboxEvent::count())->toBe(0);
});

test('missing token is rejected without settlement', function () {
    $bill    = flipBill();
    $attempt = flipAttempt($bill, '2502091430251230005');

    postFlipCallback($this, flipCallbackPayload($attempt, 150000, 'SUCCESSFUL'), null)
        ->assertStatus(200);

    expect($bill->fresh()->status)->toBe('UNPAID');
});

// ── D. Malformed payload ────────────────────────────────────────────────────

test('malformed data JSON is rejected without settlement', function () {
    $bill = flipBill();
    flipAttempt($bill, '2502091430251230005');

    $this->call('POST', '/siswa/payment/flip-callback', [
        'data'  => '{not-valid-json',
        'token' => 'flip_test_token_123',
    ], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'])
        ->assertStatus(200);

    expect($bill->fresh()->status)->toBe('UNPAID');
});

// ── E. Unknown reference ────────────────────────────────────────────────────

test('unknown bill_link_id is rejected without settlement', function () {
    flipBill();

    postFlipCallback($this, [
        'link_id' => '9999999999999999999',
        'status'  => 'SUCCESSFUL',
        'amount'  => 150000,
    ])->assertStatus(200);

    expect(StudentBill::where('status', 'PAID')->count())->toBe(0);
    expect(IntegrationOutboxEvent::count())->toBe(0);
});

// ── F. Amount mismatch ──────────────────────────────────────────────────────

test('amount mismatch is rejected without settlement or outbox', function () {
    $bill    = flipBill(150000);
    $attempt = flipAttempt($bill, '2502091430251230005');

    postFlipCallback($this, flipCallbackPayload($attempt, 999999, 'SUCCESSFUL'))
        ->assertStatus(200);

    expect($bill->fresh()->status)->toBe('UNPAID');
    expect(IntegrationOutboxEvent::count())->toBe(0);
});

// ── G/H. Status handling ────────────────────────────────────────────────────

test('FAILED status closes attempt without settling bill', function () {
    $bill    = flipBill();
    $attempt = flipAttempt($bill, '2502091430251230005');

    postFlipCallback($this, flipCallbackPayload($attempt, 150000, 'FAILED'))
        ->assertStatus(200);

    expect($bill->fresh()->status)->toBe('UNPAID')
        ->and($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_EXPIRE)
        ->and($attempt->fresh()->provider_status)->toBe('FAILED');
    expect(IntegrationOutboxEvent::count())->toBe(0);
});

test('PENDING status leaves attempt open and bill unpaid', function () {
    $bill    = flipBill();
    $attempt = flipAttempt($bill, '2502091430251230005');

    postFlipCallback($this, flipCallbackPayload($attempt, 150000, 'PENDING'))
        ->assertStatus(200);

    expect($bill->fresh()->status)->toBe('UNPAID')
        ->and($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_PENDING);
});

// ── I. Duplicate callbacks ──────────────────────────────────────────────────

test('duplicate callbacks (2x, 5x, 10x) settle exactly once', function () {
    $bill    = flipBill(150000);
    $attempt = flipAttempt($bill, '2502091430251230005');
    $payload = flipCallbackPayload($attempt, 150000, 'SUCCESSFUL');

    for ($i = 0; $i < 17; $i++) { // 2 + 5 + 10
        postFlipCallback($this, $payload)->assertStatus(200);
    }

    expect($bill->fresh()->status)->toBe('PAID');
    expect(IntegrationOutboxEvent::where('source_record_id', (string) $bill->id)
        ->where('event_type', 'spp.payment')->count())->toBe(1);
});

// ── J. Already-PAID idempotency ─────────────────────────────────────────────

test('callback after PAID does not overwrite paid_at', function () {
    $bill    = flipBill(150000);
    $attempt = flipAttempt($bill, '2502091430251230005');
    $payload = flipCallbackPayload($attempt, 150000, 'SUCCESSFUL');

    postFlipCallback($this, $payload)->assertStatus(200);
    $firstPaidAt = $bill->fresh()->paid_at;

    sleep(1);
    postFlipCallback($this, $payload)->assertStatus(200);

    expect($bill->fresh()->paid_at->equalTo($firstPaidAt))->toBeTrue();
    expect(IntegrationOutboxEvent::count())->toBe(1);
});

// ── K. 19-digit reference resolution ────────────────────────────────────────

test('resolves a 19-digit bill_link_id exactly as a string', function () {
    $linkId  = '2502091430251239999'; // 19 digits
    $bill    = flipBill(150000);
    $attempt = flipAttempt($bill, $linkId);

    postFlipCallback($this, flipCallbackPayload($attempt, 150000, 'SUCCESSFUL'))
        ->assertStatus(200);

    expect($bill->fresh()->status)->toBe('PAID');
    $ref = IntegrationOutboxEvent::first()->payload['provider_reference'];
    expect($ref)->toBe($linkId)->and(strlen($ref))->toBe(19);
});
