<?php

namespace App\Services\Integration;

use App\Models\IntegrationOutboxEvent;

/**
 * Phase 09D.2 — transport boundary for outbox delivery.
 *
 * The dispatcher depends on this abstraction; the concrete HMAC-HTTPS-to-Finance
 * implementation belongs to Phase 09D.3 and is NOT implemented here. For this
 * phase a Null/local transport (below) is used so no network call ever leaves
 * TU. Tests may substitute an in-memory fake.
 *
 * A TransportResult carries a deterministic outcome so the dispatcher can map
 * it to the outbox state machine without knowing transport internals.
 */
interface IntegrationTransport
{
    /**
     * Attempt to deliver an outbox event. MUST NOT throw for ordinary delivery
     * failures; return a TransportResult describing the outcome.
     */
    public function deliver(IntegrationOutboxEvent $event): TransportResult;
}
