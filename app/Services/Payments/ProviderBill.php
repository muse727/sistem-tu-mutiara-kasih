<?php

namespace App\Services\Payments;

/**
 * Phase E.1 — Immutable value object for a created provider bill.
 *
 * reference and payment_url come from the provider's create-bill response.
 * All identifiers are strings (19-digit safe).
 */
class ProviderBill
{
    public function __construct(
        public readonly string $reference,   // Flip link_id (string)
        public readonly string $paymentUrl,  // Flip link_url (customer payment page)
        public readonly string $status,      // provider status at creation (e.g. ACTIVE)
        public readonly array  $raw = [],    // raw response for audit/debug (never exposes secret)
    ) {}
}
