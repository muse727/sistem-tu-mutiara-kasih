<?php

namespace App\Services\Integration;

/**
 * Phase 09C — TU → Finance integration contract validator.
 *
 * Validates an integration EVENT against the contract rules defined in
 * docs/TU-FINANCE-INTEGRATION-CONTRACT.md and
 * docs/contracts/tu-finance-v1.schema.json.
 *
 * CONTRACT VALIDATION ONLY. This does NOT call Finance, create Finance
 * records, journals, or GL entries, and does NOT implement any provider.
 * It exists so the contract's acceptance/rejection semantics are executable
 * and testable before any real integration phase.
 */
class TuFinanceContractValidator
{
    // Contract error taxonomy (docs §18).
    public const INVALID_EVENT                = 'INVALID_EVENT';
    public const INVALID_IDENTITY             = 'INVALID_IDENTITY';
    public const INVALID_AMOUNT               = 'INVALID_AMOUNT';
    public const INVALID_REFERENCE            = 'INVALID_REFERENCE';
    public const UNSUPPORTED_EVENT_VERSION    = 'UNSUPPORTED_EVENT_VERSION';

    public const CONTRACT_VERSION = '1.0';
    public const SOURCE_SYSTEM    = 'TU';

    // Supported Finance integration events (Phase 09D.6-FIX). pos.kantin_payment
    // was removed: the product has no KANTIN Finance integration module. Unknown
    // event types are rejected as INVALID_EVENT (unsupported).
    public const EVENT_TYPES = ['spp.payment', 'pos.cash_sale'];

    /**
     * Validate an event. Returns a list of error codes ([] = valid).
     *
     * @return string[]
     */
    public static function validate(array $event): array
    {
        $errors = [];

        // ── Envelope ─────────────────────────────────────────────────────────
        foreach (['event_id', 'event_type', 'event_version', 'contract_version',
                  'source_system', 'source_record_id', 'idempotency_key',
                  'occurred_at', 'payload'] as $field) {
            if (! array_key_exists($field, $event)) {
                $errors[] = self::INVALID_EVENT;
            }
        }
        if ($errors !== []) {
            return array_values(array_unique($errors));
        }

        // event_type must be a known type.
        if (! in_array($event['event_type'], self::EVENT_TYPES, true)) {
            $errors[] = self::INVALID_EVENT;
        }

        // Version must be supported (no silent acceptance of unknown versions).
        if (($event['event_version'] ?? null) !== self::CONTRACT_VERSION
            || ($event['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            $errors[] = self::UNSUPPORTED_EVENT_VERSION;
        }

        // source_system fixed enum.
        if (($event['source_system'] ?? null) !== self::SOURCE_SYSTEM) {
            $errors[] = self::INVALID_EVENT;
        }

        // source_record_id must be present and non-empty.
        if (! is_string($event['source_record_id']) || $event['source_record_id'] === '') {
            $errors[] = self::INVALID_REFERENCE;
        }

        // idempotency_key format: TU:{event_type}:{source_record_id}.
        $expectedKey = sprintf('%s:%s:%s', self::SOURCE_SYSTEM, $event['event_type'], $event['source_record_id']);
        if (($event['idempotency_key'] ?? null) !== $expectedKey) {
            $errors[] = self::INVALID_EVENT;
        }

        // occurred_at must parse as a date.
        if (! self::isDateTime($event['occurred_at'] ?? null)) {
            $errors[] = self::INVALID_EVENT;
        }

        // ── Payload (per event type) ──────────────────────────────────────────
        $payload = $event['payload'] ?? null;
        if (! is_array($payload)) {
            $errors[] = self::INVALID_EVENT;
            return array_values(array_unique($errors));
        }

        $errors = array_merge($errors, self::validatePayload($event['event_type'], $payload));

        return array_values(array_unique($errors));
    }

    /** Validate an event; throw-style convenience returning first error or null. */
    public static function firstError(array $event): ?string
    {
        $errors = self::validate($event);
        return $errors[0] ?? null;
    }

    /** True when the event passes all contract rules. */
    public static function isValid(array $event): bool
    {
        return self::validate($event) === [];
    }

    /**
     * Deterministic idempotency key for an operational fact.
     * This is the canonical duplicate-detection boundary for the consumer.
     */
    public static function idempotencyKey(string $eventType, string $sourceRecordId): string
    {
        return sprintf('%s:%s:%s', self::SOURCE_SYSTEM, $eventType, $sourceRecordId);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private static function validatePayload(string $eventType, array $p): array
    {
        $errors = [];

        switch ($eventType) {
            case 'spp.payment':
                if (! isset($p['student']['student_uuid']) || ! self::isUuid($p['student']['student_uuid'])) {
                    $errors[] = self::INVALID_IDENTITY;
                }
                if (empty($p['bill_id'])) {
                    $errors[] = self::INVALID_REFERENCE;
                }
                if (! self::isDecimalMoney($p['amount'] ?? null)) {
                    $errors[] = self::INVALID_AMOUNT;
                }
                $errors = array_merge($errors, self::validateProvider($p));
                if (! self::isDateTime($p['paid_at'] ?? null)) {
                    $errors[] = self::INVALID_EVENT;
                }
                break;

            case 'pos.cash_sale':
                if (empty($p['transaction_code'])) {
                    $errors[] = self::INVALID_REFERENCE;
                }
                foreach (['amount', 'payment_amount', 'change_amount'] as $f) {
                    if (! is_int($p[$f] ?? null) || $p[$f] < 0) {
                        $errors[] = self::INVALID_AMOUNT;
                    }
                }
                if (($p['payment_method'] ?? null) !== 'CASH'
                    || self::val($p, 'provider') !== null
                    || self::val($p, 'provider_reference') !== null) {
                    $errors[] = self::INVALID_EVENT; // cash must have null provider
                }
                // student is OPTIONAL for cash sale, but if present must have a uuid.
                if (isset($p['student']) && $p['student'] !== null
                    && ! self::isUuid($p['student']['student_uuid'] ?? null)) {
                    $errors[] = self::INVALID_IDENTITY;
                }
                break;

            default:
                $errors[] = self::INVALID_EVENT;
        }

        return $errors;
    }

    /** Validate provider semantics (distinguish provider / method / reference). */
    private static function validateProvider(array $p): array
    {
        $errors = [];
        $method   = $p['payment_method'] ?? null;
        $provider = $p['provider'] ?? null;
        $ref      = $p['provider_reference'] ?? null;

        if ($method === 'CASH') {
            // Cash → provider and reference must be null.
            if ($provider !== null || $ref !== null) {
                $errors[] = self::INVALID_EVENT;
            }
        } elseif ($method === 'MIDTRANS') {
            // Provider-settled → provider + reference required (historical).
            if ($provider !== 'midtrans' || ! is_string($ref) || $ref === '') {
                $errors[] = self::INVALID_REFERENCE;
            }
        } elseif ($method === 'ONLINE') {
            // Provider-settled online payment (Phase E.1). Provider must be a
            // known online provider; reference is a non-empty string. FLIP is the
            // active provider; MIDTRANS remains valid for historical records.
            if (! in_array($provider, ['flip', 'midtrans'], true)
                || ! is_string($ref) || $ref === '') {
                $errors[] = self::INVALID_REFERENCE;
            }
        } else {
            $errors[] = self::INVALID_EVENT;
        }

        return $errors;
    }

    private static function isUuid($v): bool
    {
        return is_string($v)
            && preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $v) === 1;
    }

    private static function isDecimalMoney($v): bool
    {
        // SPP amount: positive decimal string with exactly 2dp. No negatives/zero.
        return is_string($v) && preg_match('/^[0-9]+\.[0-9]{2}$/', $v) === 1 && (float) $v > 0;
    }

    private static function isDateTime($v): bool
    {
        if (! is_string($v) || $v === '') {
            return false;
        }
        return strtotime($v) !== false;
    }

    /**
     * Read a payload key distinguishing "present and null" from "missing".
     * PHP's ?? treats explicit null as absent, so for fields where null is a
     * meaningful value (provider for cash) we must use array_key_exists.
     * Returns the value, or a sentinel '__missing__' string when absent.
     */
    private static function val(array $p, string $key)
    {
        return array_key_exists($key, $p) ? $p[$key] : '__missing__';
    }
}
