<?php

namespace App\Services\Payments;

/**
 * Phase E.1 — Payment provider boundary.
 *
 * Minimal abstraction for an online payment provider. The Flip adapter
 * implements this contract. Business logic (settling StudentBill, outbox,
 * Finance) remains OUTSIDE the adapter.
 *
 * The adapter MUST NOT:
 *   - mark StudentBill PAID
 *   - create outbox events
 *   - call Finance
 *   - create journals
 *
 * All provider identifiers (e.g. Flip link_id, which may be a 19-digit
 * value) are treated as STRINGS — never cast to int/float.
 */
interface PaymentProviderInterface
{
    /** Provider key stored on PaymentAttempt.provider */
    public function providerKey(): string;

    /**
     * Create a hosted payment bill.
     *
     * @param  int    $amountIdr  Authoritative amount in whole IDR (integer).
     * @param  string $title      Bill title shown to the customer.
     * @param  array  $options    step, sender_name, sender_email, redirect_url, expired_date...
     * @return ProviderBill       Parsed provider bill (reference + payment URL).
     *
     * @throws ProviderException on transport/auth/validation failure.
     */
    public function createBill(int $amountIdr, string $title, array $options = []): ProviderBill;

    /**
     * Query a bill's current status from the provider (status recovery).
     *
     * @param  string $providerReference  Flip link_id (string).
     * @return ProviderStatus
     *
     * @throws ProviderException on transport/auth failure.
     */
    public function queryStatus(string $providerReference): ProviderStatus;

    /**
     * Verify an inbound callback's transport authenticity (token).
     * Uses constant-time comparison. Returns true only on exact match.
     *
     * @param  string|null $incomingToken  token field from the callback payload.
     */
    public function verifyCallbackToken(?string $incomingToken): bool;

    /**
     * Map a provider status string to a canonical internal status.
     * One of: 'success', 'pending', 'failed'.
     */
    public function mapStatus(string $providerStatus): string;
}
