<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;

/**
 * Phase E.1 — Flip Accept Payment V2 adapter (SANDBOX ONLY).
 *
 * Empirically verified against the live sandbox (docs/PHASE-E0.2-FLIP-V2-CONTRACT.md):
 *   Base URL      : https://bigflip.id/big_sandbox_api
 *   Create Bill   : POST /v2/pwf/bill  → 200 { link_id, link_url, status:"ACTIVE", ... }
 *                   - step=1 : no sender fields required
 *                   - step=2 : requires sender_name AND sender_email
 *                   - link_id is the provider reference (keep as STRING; never cast)
 *   Status inquiry: GET  /v2/pwf/{link_id}/bill  → 200 { link_id, status, amount, ... }.
 *                   - GET /v2/pwf/bill/{link_id} → 404 (NOT the inquiry path)
 *                   - GET /v2/pwf/bill           → the full list (avoid in prod)
 *                   - creation status is "ACTIVE"; settled bills report "SUCCESSFUL"
 *   Auth          : HTTP Basic  base64(secret . ':')  (secret=username, empty password)
 *
 * The secret is sourced from environment config ONLY. It is never logged, never
 * placed in the frontend, never stored in the DB, never returned in an API
 * response, and the Authorization header is never logged.
 */
class FlipPaymentProvider implements PaymentProviderInterface
{
    public const PROVIDER_KEY = 'FLIP';

    // ── Flip V2 status strings (verified against sandbox) ───────────────────
    // Creation returns ACTIVE (awaiting payment). Terminal/settled = SUCCESSFUL.
    public const STATUS_ACTIVE     = 'ACTIVE';     // awaiting payment (creation/list)
    public const STATUS_PENDING    = 'PENDING';    // alternate pending label (defensive)
    public const STATUS_SUCCESSFUL = 'SUCCESSFUL'; // settled — may mark PAID
    public const STATUS_FAILED     = 'FAILED';
    public const STATUS_CANCELLED  = 'CANCELLED';
    public const STATUS_EXPIRED    = 'EXPIRED';

    /** Only SUCCESSFUL may settle a bill as PAID. */
    public const SUCCESS_STATUSES = [self::STATUS_SUCCESSFUL];

    /** Terminal non-settling statuses. */
    public const FAILED_STATUSES = [
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    public function __construct(
        private readonly ?string $baseUrl      = null,
        private readonly ?string $secretKey    = null,
        private readonly ?string $callbackToken = null,
        private readonly int     $timeout      = 15,
    ) {}

    private function baseUrl(): string
    {
        return rtrim($this->baseUrl ?? (string) config('services.flip.api_url'), '/');
    }

    private function secretKey(): string
    {
        $key = $this->secretKey ?? (string) config('services.flip.secret_key');
        if ($key === '') {
            throw new ProviderException('Flip secret key is not configured.');
        }
        return $key;
    }

    private function callbackToken(): string
    {
        return $this->callbackToken ?? (string) config('services.flip.callback_token');
    }

    public function providerKey(): string
    {
        return self::PROVIDER_KEY;
    }

    // ── 1. Create Bill V2 ────────────────────────────────────────────────────

    public function createBill(int $amountIdr, string $title, array $options = []): ProviderBill
    {
        if ($amountIdr <= 0) {
            throw new ProviderException('Amount must be a positive integer (IDR).');
        }

        // Only send fields that are actually provided. step defaults to 1
        // (input step) because step=2 requires sender_name + sender_email.
        $body = array_filter([
            'title'         => $title,
            'type'          => $options['type']          ?? 'SINGLE',
            'amount'        => $amountIdr,
            'step'          => $options['step']          ?? 1,
            'redirect_url'  => $options['redirect_url']  ?? null,
            'expired_date'  => $options['expired_date']  ?? null,
            'sender_name'   => $options['sender_name']   ?? null,
            'sender_email'  => $options['sender_email']  ?? null,
            'sender_phone_number' => $options['sender_phone_number'] ?? null,
            'sender_address'=> $options['sender_address']?? null,
        ], static fn ($v) => $v !== null);

        $response = $this->client()->asForm()->post($this->baseUrl() . '/v2/pwf/bill', $body);

        return $this->parseCreateResponse($response);
    }

    // ── 2. Status inquiry (recovery) ─────────────────────────────────────────
    // Verified sandbox endpoint: GET /v2/pwf/{link_id}/bill  (single bill).
    // NOTE: GET /v2/pwf/bill/{link_id} 404s; GET /v2/pwf/bill is the full list.

    public function queryStatus(string $providerReference): ProviderStatus
    {
        $providerReference = trim($providerReference);
        if ($providerReference === '') {
            throw new ProviderException('providerReference (link_id) is required.');
        }

        $response = $this->client()
            ->get($this->baseUrl() . '/v2/pwf/' . rawurlencode($providerReference) . '/bill');

        if ($response->status() === 404) {
            throw new ProviderException('Flip bill not found.', 404, 'NOT_FOUND');
        }
        if ($response->failed()) {
            throw new ProviderException(
                'Flip status inquiry failed (HTTP ' . $response->status() . ').',
                $response->status()
            );
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new ProviderException('Flip status inquiry returned a non-object payload.');
        }

        $status = (string) ($data['status'] ?? '');
        $ref    = isset($data['link_id']) ? (string) $data['link_id'] : $providerReference;

        return new ProviderStatus(
            reference: $ref,
            providerStatus: $status,
            canonical: $this->mapStatus($status),
            amountIdr: isset($data['amount']) ? (int) $data['amount'] : null,
            raw: $this->redact($data),
        );
    }

    // ── 3. Callback token verification (constant-time) ───────────────────────

    public function verifyCallbackToken(?string $incomingToken): bool
    {
        $expected = $this->callbackToken();
        if ($expected === '' || $incomingToken === null || $incomingToken === '') {
            return false;
        }
        return hash_equals($expected, (string) $incomingToken);
    }

    // ── 4. Status mapping ────────────────────────────────────────────────────

    public function mapStatus(string $providerStatus): string
    {
        $s = strtoupper(trim($providerStatus));
        return match (true) {
            in_array($s, self::SUCCESS_STATUSES, true) => 'success',
            in_array($s, self::FAILED_STATUSES,  true) => 'failed',
            // ACTIVE / PENDING / unknown → still awaiting payment.
            default                                    => 'pending',
        };
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function client()
    {
        $basic = base64_encode($this->secretKey() . ':');
        return Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Basic ' . $basic,
                'Accept'        => 'application/json',
            ])
            ->withoutRedirecting();
    }

    private function parseCreateResponse($response): ProviderBill
    {
        $data = $response->json() ?? [];

        // Flip returns VALIDATION_ERROR as HTTP 4xx with an errors array.
        if ($response->failed() || ! empty($data['errors'])) {
            $first = is_array($data['errors'][0] ?? null) ? $data['errors'][0] : [];
            throw new ProviderException(
                'Flip create-bill failed: ' . ($first['message'] ?? ('HTTP ' . $response->status())),
                $response->status(),
                isset($first['code']) ? (string) $first['code'] : null
            );
        }

        // Verified create response uses link_id + link_url (status ACTIVE).
        // Also accept bill_link_id defensively. Keep reference as STRING.
        $reference  = $data['link_id'] ?? $data['bill_link_id'] ?? null;
        $reference  = $reference === null ? '' : (string) $reference;
        $paymentUrl = (string) ($data['link_url'] ?? $data['bill_link'] ?? '');
        $status     = (string) ($data['status'] ?? self::STATUS_ACTIVE);

        if ($reference === '' || $paymentUrl === '') {
            throw new ProviderException('Flip create-bill response missing link_id or link_url.');
        }

        return new ProviderBill(
            reference: $reference,
            paymentUrl: $paymentUrl,
            status: $status,
            raw: $this->redact($data),
        );
    }

    private function redact(array $data): array
    {
        foreach (['secret', 'secret_key', 'authorization', 'token', 'api_key'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = '[REDACTED]';
            }
        }
        return $data;
    }
}
