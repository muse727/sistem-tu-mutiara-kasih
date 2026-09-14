<?php

namespace App\Services\Payments;

/**
 * Phase E.1 — Immutable value object for a provider status inquiry result.
 */
class ProviderStatus
{
    public function __construct(
        public readonly string $reference,      // Flip link_id (string)
        public readonly string $providerStatus, // raw provider status, e.g. SUCCESSFUL/PENDING/FAILED
        public readonly string $canonical,      // 'success' | 'pending' | 'failed'
        public readonly ?int   $amountIdr = null,
        public readonly array  $raw = [],
    ) {}
}
