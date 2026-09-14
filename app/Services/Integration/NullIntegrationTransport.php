<?php

namespace App\Services\Integration;

use App\Models\IntegrationOutboxEvent;

/**
 * Phase 09D.2 — local/null transport. NEVER performs a network call; Finance is
 * not connected in this phase. Every event is reported as retryable-unavailable
 * so the outbox records it as pending/retryable and the source truth is never
 * affected. Replaced by the HMAC-HTTPS transport in Phase 09D.3.
 */
class NullIntegrationTransport implements IntegrationTransport
{
    public function deliver(IntegrationOutboxEvent $event): TransportResult
    {
        return TransportResult::retryable('transport-not-configured');
    }
}
