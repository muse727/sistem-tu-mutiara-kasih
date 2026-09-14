<?php

namespace App\Services\Integration;

use App\Models\IntegrationOutboxEvent;
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
                ->first();

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
    }
}
