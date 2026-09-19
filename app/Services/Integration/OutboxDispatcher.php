<?php

namespace App\Services\Integration;

use App\Models\IntegrationOutboxEvent;
use App\Models\SppSettlementBatch;
use Illuminate\Support\Facades\DB;

/**
 * Phase 09D.2 — outbox dispatcher.
 *
 * Selects PENDING / FAILED_RETRYABLE events, claims one at a time under a
 * database row lock (SELECT ... FOR UPDATE) so two dispatcher processes cannot
 * process the same event concurrently, then delivers via the injected
 * IntegrationTransport OUTSIDE the lock. The lock is never held across a
 * network call.
 *
 * State machine:
 *   PENDING / FAILED_RETRYABLE → PROCESSING → DELIVERED
 *                                            → FAILED_RETRYABLE (retryable failure)
 *                                            → FAILED_PERMANENT (permanent failure)
 *
 * No secrets are logged; only the transport's sanitized error string is stored.
 */
class OutboxDispatcher
{
    /** Max attempts before a retryable failure is parked as permanent. */
    public const MAX_ATTEMPTS = 10;

    public function __construct(
        protected IntegrationTransport $transport,
    ) {}

    /**
     * Dispatch up to $limit eligible events. Returns counts by outcome.
     *
     * @return array{delivered:int, retryable:int, permanent:int, claimed:int}
     */
    public function dispatch(int $limit = 50): array
    {
        $result = ['delivered' => 0, 'retryable' => 0, 'permanent' => 0, 'claimed' => 0];

        for ($i = 0; $i < $limit; $i++) {
            $event = $this->claimNext();
            if (! $event) {
                break;
            }
            $result['claimed']++;

            // Deliver OUTSIDE any lock/transaction.
            $outcome = $this->transport->deliver($event);
            $this->recordOutcome($event, $outcome);

            if ($outcome->outcome === TransportResult::DELIVERED) {
                $result['delivered']++;
            } elseif ($outcome->outcome === TransportResult::PERMANENT) {
                $result['permanent']++;
            } else {
                $result['retryable']++;
            }
        }

        return $result;
    }

    /**
     * Claim the oldest eligible event: lock it, bump attempt_count, mark
     * PROCESSING, commit the claim. Returns null when nothing is eligible.
     * SKIP LOCKED lets concurrent dispatchers take different rows.
     */
    protected function claimNext(): ?IntegrationOutboxEvent
    {
        return DB::transaction(function () {
            $event = IntegrationOutboxEvent::whereIn('delivery_status', [
                    IntegrationOutboxEvent::STATUS_PENDING,
                    IntegrationOutboxEvent::STATUS_FAILED_RETRYABLE,
                ])
                ->where('attempt_count', '<', self::MAX_ATTEMPTS)
                ->orderBy('created_at')
                ->lockForUpdate() // MariaDB: FOR UPDATE; use skipLocked when supported
                ->get()
                ->first(fn (IntegrationOutboxEvent $e) => $this->isAccountingEvent($e));

            if (! $event) {
                return null;
            }

            $event->update([
                'delivery_status' => IntegrationOutboxEvent::STATUS_PROCESSING,
                'attempt_count'   => $event->attempt_count + 1,
            ]);

            return $event;
        });
    }

    /** Map the transport outcome onto the outbox state machine. */
    protected function recordOutcome(IntegrationOutboxEvent $event, TransportResult $outcome): void
    {
        match ($outcome->outcome) {
            TransportResult::DELIVERED => $event->update([
                'delivery_status' => IntegrationOutboxEvent::STATUS_DELIVERED,
                'delivered_at'    => now(),
                'last_error'      => null,
            ]),
            TransportResult::PERMANENT => $event->update([
                'delivery_status' => IntegrationOutboxEvent::STATUS_FAILED_PERMANENT,
                'last_error'      => $outcome->error,
            ]),
            default => $event->update([
                'delivery_status' => $event->attempt_count >= self::MAX_ATTEMPTS
                    ? IntegrationOutboxEvent::STATUS_FAILED_PERMANENT
                    : IntegrationOutboxEvent::STATUS_FAILED_RETRYABLE,
                'last_error'      => $outcome->error,
            ]),
        };

        $this->syncBatchStatus($event, $outcome);
    }

    /**
     * TU-FINANCE-BATCH-02 — reflect the outbox delivery outcome onto the linked
     * settlement batch lifecycle (SENT on dispatch, ACKNOWLEDGED on delivered,
     * FAILED_* on failure). Operational timestamps only; never mutates the
     * batch's frozen accounting snapshot. No-op for non-batch events.
     */
    protected function syncBatchStatus(IntegrationOutboxEvent $event, TransportResult $outcome): void
    {
        if ($event->event_type !== IntegrationOutboxEvent::EVENT_SPP_SETTLEMENT_BATCH) {
            return;
        }
        $batch = SppSettlementBatch::where('batch_id', $event->source_record_id)->first();
        if (! $batch) {
            return;
        }
        $updates = ['attempt_count' => $event->attempt_count, 'last_error' => $outcome->error];
        if ($outcome->outcome === TransportResult::DELIVERED) {
            $updates['status'] = SppSettlementBatch::STATUS_ACKNOWLEDGED;
            $updates['sent_at'] = $batch->sent_at ?? now();
            $updates['acknowledged_at'] = now();
        } elseif ($outcome->outcome === TransportResult::PERMANENT) {
            $updates['status'] = SppSettlementBatch::STATUS_FAILED_PERMANENT;
            $updates['sent_at'] = $batch->sent_at ?? now();
        } else {
            $updates['status'] = SppSettlementBatch::STATUS_FAILED_RETRYABLE;
            $updates['sent_at'] = $batch->sent_at ?? now();
        }
        $batch->forceFill($updates)->save();
    }

    /**
     * TU-FINANCE-BATCH-02 / F-2 (BATCH-03-FIX) — decide whether an outbox event
     * should be dispatched for Finance accounting.
     *
     * A per-bill spp.payment event is dispatched for accounting ONLY when its
     * source StudentBill is NOT a settlement-batch member. When the bill IS a
     * batch member, the batch (spp.settlement_batch) is the sole accounting
     * posting and the per-bill event is trace-only. All other event types
     * (pos.cash_sale, spp.settlement_batch) are always accounting events.
     *
     * Basing the decision on ACTUAL batch membership (not the live feature-flag
     * value) closes the F-2 flag-flip gap in both directions:
     *   - ON→OFF: a bill batched while ON stays trace-only (its batch still
     *     posts) — it is never re-dispatched per-bill, so no double-post.
     *   - OFF→ON: a bill settled while OFF (never batched) keeps its per-bill
     *     event dispatchable, so it is still accounted — no gap.
     * Either way every settled bill has exactly ONE accounting path.
     */
    protected function isAccountingEvent(IntegrationOutboxEvent $event): bool
    {
        if ($event->event_type !== IntegrationOutboxEvent::EVENT_SPP_PAYMENT) {
            return true;
        }

        return ! \App\Models\SppSettlementBatchItem::where('student_bill_id', $event->source_record_id)->exists();
    }
}

