<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 09D.2 — TU transactional integration outbox event.
 *
 * Immutable identity + business payload once created; only operational fields
 * (status, attempt_count, last_error, delivered_at) may change after creation.
 */
class IntegrationOutboxEvent extends Model
{
    protected $table = 'integration_outbox_events';

    public const STATUS_PENDING          = 'PENDING';
    public const STATUS_PROCESSING       = 'PROCESSING';
    public const STATUS_DELIVERED        = 'DELIVERED';
    public const STATUS_FAILED_RETRYABLE = 'FAILED_RETRYABLE';
    public const STATUS_FAILED_PERMANENT = 'FAILED_PERMANENT';

    // Supported Finance integration events (Phase 09D.6-FIX). pos.kantin_payment
    // was removed: the product has no KANTIN Finance integration module.
    public const EVENT_SPP_PAYMENT       = 'spp.payment';
    public const EVENT_POS_CASH_SALE     = 'pos.cash_sale';
    // TU-FINANCE-BATCH-02 — consolidated SPP settlement batch.
    public const EVENT_SPP_SETTLEMENT_BATCH = 'spp.settlement_batch';

    protected $fillable = [
        'event_id', 'event_type', 'event_version', 'contract_version',
        'source_system', 'source_record_id', 'idempotency_key', 'occurred_at',
        'payload', 'delivery_status', 'attempt_count', 'last_error', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'occurred_at'  => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Guard immutability: identity + business payload never change after create.
     * Only delivery/claim metadata may be updated.
     */
    protected static function booted(): void
    {
        static::updating(function (self $event) {
            $immutable = ['event_id', 'event_type', 'event_version', 'contract_version',
                          'source_system', 'source_record_id', 'idempotency_key', 'occurred_at', 'payload'];
            foreach ($immutable as $field) {
                if ($event->isDirty($field)) {
                    throw new \LogicException("Outbox event field '{$field}' is immutable.");
                }
            }
        });
    }

    /** Canonical idempotency key for the TU contract. */
    public static function idempotencyKey(string $eventType, string|int $sourceRecordId): string
    {
        return sprintf('TU:%s:%s', $eventType, $sourceRecordId);
    }
}
