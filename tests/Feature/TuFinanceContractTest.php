<?php

use App\Services\Integration\TuFinanceContractValidator as V;

/**
 * Phase 09C — TU → Finance integration contract validation.
 *
 * Validates the CONTRACT (acceptance/rejection semantics), not Finance.
 * Loads the three example events and asserts they are valid, then asserts
 * rejection/duplicate cases behave per the contract error taxonomy.
 */

function loadExample(string $name): array
{
    $path = base_path("docs/contracts/examples/{$name}.json");
    return json_decode(file_get_contents($path), true);
}

// ── Valid examples pass the contract ─────────────────────────────────────────
test('SPP payment example is a valid contract event', function () {
    expect(V::isValid(loadExample('spp-payment.example')))->toBeTrue();
});

test('POS cash sale example is a valid contract event', function () {
    expect(V::isValid(loadExample('pos-cash-sale.example')))->toBeTrue();
});

// ── Identity ─────────────────────────────────────────────────────────────────
test('missing student_uuid is rejected as INVALID_IDENTITY', function () {
    $e = loadExample('spp-payment.example');
    unset($e['payload']['student']['student_uuid']);
    expect(V::validate($e))->toContain(V::INVALID_IDENTITY);
});

test('name is never identity — a different name with same uuid stays valid', function () {
    $e = loadExample('spp-payment.example');
    $e['payload']['student']['name'] = 'Totally Different Name';
    expect(V::isValid($e))->toBeTrue();
});

// ── Source reference ──────────────────────────────────────────────────────────
test('missing source_record_id is rejected as INVALID_REFERENCE', function () {
    $e = loadExample('spp-payment.example');
    $e['source_record_id'] = '';
    expect(V::validate($e))->toContain(V::INVALID_REFERENCE);
});

// ── Amount ───────────────────────────────────────────────────────────────────
test('invalid amount (zero / negative / wrong format) is rejected as INVALID_AMOUNT', function () {
    $e = loadExample('spp-payment.example');

    $e['payload']['amount'] = '0.00';
    expect(V::validate($e))->toContain(V::INVALID_AMOUNT);

    $e['payload']['amount'] = '-100.00';
    expect(V::validate($e))->toContain(V::INVALID_AMOUNT);

    $e['payload']['amount'] = '150000'; // missing 2dp → not SPP decimal money
    expect(V::validate($e))->toContain(V::INVALID_AMOUNT);
});

// ── Event type / version ─────────────────────────────────────────────────────
test('unknown event_type is rejected as INVALID_EVENT', function () {
    $e = loadExample('spp-payment.example');
    $e['event_type'] = 'spp.checkout_started'; // not an integration event
    expect(V::validate($e))->toContain(V::INVALID_EVENT);
});

test('unsupported version is rejected as UNSUPPORTED_EVENT_VERSION', function () {
    $e = loadExample('spp-payment.example');
    $e['event_version'] = '2.0';
    expect(V::validate($e))->toContain(V::UNSUPPORTED_EVENT_VERSION);
});

// ── Provider semantics ────────────────────────────────────────────────────────
test('malformed provider data is rejected', function () {
    // Cash method must have null provider/reference.
    $e = loadExample('spp-payment.example');
    $e['payload']['payment_method'] = 'CASH';
    $e['payload']['provider'] = 'midtrans'; // inconsistent
    expect(V::validate($e))->toContain(V::INVALID_EVENT);

    // MIDTRANS method must have provider + reference.
    $e2 = loadExample('spp-payment.example');
    $e2['payload']['provider_reference'] = null;
    expect(V::validate($e2))->toContain(V::INVALID_REFERENCE);
});

// ── Duplicate / idempotency semantics ────────────────────────────────────────
test('idempotency key is deterministic and unique per event type + source record', function () {
    $k1 = V::idempotencyKey('spp.payment', '5012');
    $k2 = V::idempotencyKey('spp.payment', '5012');
    $k3 = V::idempotencyKey('pos.cash_sale', '5012'); // same record id, different type

    // Deterministic: same input → same key.
    expect($k1)->toBe($k2);
    // Same source_record_id but different event types → different keys.
    expect($k1)->not->toBe($k3);
});

test('event idempotency_key must match its own type + source_record_id', function () {
    $e = loadExample('spp-payment.example');
    // Tamper: point the key at a different source record.
    $e['idempotency_key'] = 'TU:spp.payment:9999';
    expect(V::validate($e))->toContain(V::INVALID_EVENT);
});

// ── POS cash specifics ────────────────────────────────────────────────────────
test('POS cash sale with anonymous (null) student is valid', function () {
    $e = loadExample('pos-cash-sale.example');
    expect($e['payload']['student'])->toBeNull();
    expect(V::isValid($e))->toBeTrue();
});

test('POS cash sale rejects a negative change_amount', function () {
    $e = loadExample('pos-cash-sale.example');
    $e['payload']['change_amount'] = -1;
    expect(V::validate($e))->toContain(V::INVALID_AMOUNT);
});

