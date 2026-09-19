<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TU-FINANCE-BATCH-02 — SPP settlement batch aggregate.
 *
 * Groups settled SPP receipts over a period into ONE Finance-facing object so
 * Finance posts one balanced journal per batch (cash basis) instead of one
 * transaction per StudentBill.
 *
 * Lifecycle: DRAFT → READY_TO_CLOSE → CLOSED → SENT → ACKNOWLEDGED, with
 * FAILED_RETRYABLE / FAILED_PERMANENT. A CLOSED (or later) batch's accounting
 * snapshot (total_amount, counts, breakdown, idempotency identity, period) is
 * immutable — never reopened; corrections are new batches. Operational fields
 * (status, attempt_count, last_error, sent/acknowledged timestamps) may change.
 */
class SppSettlementBatch extends Model
{
    protected $table = 'spp_settlement_batches';

    public const STATUS_DRAFT            = 'DRAFT';
    public const STATUS_READY_TO_CLOSE   = 'READY_TO_CLOSE';
    public const STATUS_CLOSED           = 'CLOSED';
    public const STATUS_SENT             = 'SENT';
    public const STATUS_ACKNOWLEDGED     = 'ACKNOWLEDGED';
    public const STATUS_FAILED_RETRYABLE = 'FAILED_RETRYABLE';
    public const STATUS_FAILED_PERMANENT = 'FAILED_PERMANENT';

    public const PERIOD_DAILY   = 'DAILY';
    public const PERIOD_WEEKLY  = 'WEEKLY';
    public const PERIOD_MONTHLY = 'MONTHLY';

    protected $fillable = [
        'batch_id', 'idempotency_key', 'source_system', 'category',
        'period_kind', 'period_start', 'period_end',
        'total_amount', 'payment_count', 'bill_count', 'breakdown',
        'status', 'occurred_at', 'closed_at', 'sent_at', 'acknowledged_at',
        'attempt_count', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'period_start'    => 'date',
            'period_end'      => 'date',
            'total_amount'    => 'decimal:2',
            'payment_count'   => 'integer',
            'bill_count'      => 'integer',
            'breakdown'       => 'array',
            'occurred_at'     => 'datetime',
            'closed_at'       => 'datetime',
            'sent_at'         => 'datetime',
            'acknowledged_at' => 'datetime',
            'attempt_count'   => 'integer',
        ];
    }

    /** Deterministic idempotency key — the final Finance anti-duplicate guard. */
    public static function idempotencyKey(string $periodKind, string $periodStart): string
    {
        return sprintf('TU:SPP:%s:%s', $periodKind, $periodStart);
    }

    /**
     * F-1 (BATCH-03-FIX) — deterministic key for an OVERFLOW batch holding late
     * settlements that arrived after the period's canonical batch was closed.
     * Derived from the overflow batch_id so it is always unique (no collision
     * with the canonical batch's key), while remaining stable per batch.
     */
    public static function overflowIdempotencyKey(string $periodKind, string $batchId): string
    {
        return sprintf('TU:SPP:%s:OVR:%s', $periodKind, $batchId);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SppSettlementBatchItem::class, 'spp_settlement_batch_id');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_CLOSED,
            self::STATUS_SENT,
            self::STATUS_ACKNOWLEDGED,
            self::STATUS_FAILED_RETRYABLE,
            self::STATUS_FAILED_PERMANENT,
        ], true);
    }

    /** Guard: identity + accounting snapshot never change once closed. */
    protected static function booted(): void
    {
        static::updating(function (self $batch) {
            if (! $batch->isClosed() && $batch->getOriginal('status') === self::STATUS_DRAFT) {
                return; // DRAFT/READY_TO_CLOSE batches may be recomputed.
            }
            $immutable = [
                'batch_id', 'idempotency_key', 'source_system', 'category',
                'period_kind', 'period_start', 'period_end',
                'total_amount', 'payment_count', 'bill_count', 'breakdown', 'occurred_at',
            ];
            foreach ($immutable as $field) {
                if ($batch->isDirty($field)) {
                    throw new \LogicException("Settlement batch field '{$field}' is immutable once closed.");
                }
            }
        });

        static::deleting(function (self $batch) {
            if ($batch->isClosed()) {
                throw new \LogicException('A closed settlement batch cannot be deleted.');
            }
        });
    }
}
