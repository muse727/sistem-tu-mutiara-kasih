<?php

namespace App\Services\Integration;

use App\Models\IntegrationOutboxEvent;
use Illuminate\Support\Facades\Http;

/**
 * Phase 09D.3 — TU-side HMAC-HTTPS transport to Finance.
 *
 * Implements IntegrationTransport (09D.2). Signs each outbox event with HMAC
 * over the EXACT raw event JSON body and POSTs it to the Finance ingest
 * endpoint. HMAC authenticates the REQUEST; event identity/idempotency
 * authenticates the BUSINESS FACT — these stay separate. The shared secret
 * comes from config (env); it is never logged or transmitted.
 *
 * Canonical string (must match Finance's TuHmacVerifier):
 *   {timestamp}\n{METHOD}\n{path}\n{sha256_hex(raw_body)}
 * Signature = base64( HMAC-SHA256( secret, canonical ) )
 *
 * Response classification:
 *   2xx (PROCESSED/DUPLICATE) → DELIVERED   (duplicate acknowledged, no retry)
 *   408 / timeout / network   → RETRYABLE
 *   429                        → RETRYABLE
 *   5xx                        → RETRYABLE
 *   4xx (validation/auth/business) → PERMANENT (no endless retry)
 */
class HmacIntegrationTransport implements IntegrationTransport
{
    public function deliver(IntegrationOutboxEvent $event): TransportResult
    {
        $config = config('integration.finance', []);
        $url    = $config['url'] ?? null;
        $secret = $config['secret'] ?? null;
        $keyId  = $config['key_id'] ?? 'tu-local';
        $timeout = (int) ($config['timeout'] ?? 10);

        if (! $url || ! $secret) {
            return TransportResult::retryable('transport-not-configured');
        }

        $rawBody = $this->serializeEvent($event);
        $timestamp = (string) time();
        $path = parse_url($url, PHP_URL_PATH) ?: '/api/integration/tu/events';
        $signature = $this->sign($secret, $timestamp, 'POST', $path, $rawBody);

        try {
            $response = Http::timeout($timeout)
                ->withBody($rawBody, 'application/json')
                ->withHeaders([
                    'X-TU-Key-Id'    => $keyId,
                    'X-TU-Timestamp' => $timestamp,
                    'X-TU-Signature' => $signature,
                ])
                ->post($url);
        } catch (\Throwable $e) {
            // Timeout / connection failure — retryable, no secret logged.
            return TransportResult::retryable('network-error');
        }

        return $this->classify($response->status());
    }

    /** Build the canonical signing string. */
    public function canonicalString(string $timestamp, string $method, string $path, string $rawBody): string
    {
        return $timestamp . "\n"
            . strtoupper($method) . "\n"
            . $path . "\n"
            . hash('sha256', $rawBody);
    }

    /** Compute the signature (base64) over the canonical string. */
    public function sign(string $secret, string $timestamp, string $method, string $path, string $rawBody): string
    {
        return base64_encode(hash_hmac('sha256', $this->canonicalString($timestamp, $method, $path, $rawBody), $secret, true));
    }

    /**
     * Serialize the outbox event into the exact canonical contract-v1 JSON body.
     * The event payload is an immutable snapshot; only the envelope is assembled.
     */
    protected function serializeEvent(IntegrationOutboxEvent $event): string
    {
        return json_encode([
            'event_id'         => $event->event_id,
            'event_type'       => $event->event_type,
            'event_version'    => $event->event_version,
            'contract_version' => $event->contract_version,
            'source_system'    => $event->source_system,
            'source_record_id' => $event->source_record_id,
            'idempotency_key'  => $event->idempotency_key,
            'occurred_at'      => \Carbon\Carbon::parse($event->occurred_at)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i:sP'),
            'payload'          => $event->payload,
        ]);
    }

    /** Map HTTP status to a delivery outcome. */
    protected function classify(int $status): TransportResult
    {
        if ($status >= 200 && $status < 300) {
            // PROCESSED (201) or DUPLICATE (200) — both mean Finance has the event.
            return TransportResult::delivered();
        }
        if ($status === 408 || $status === 429) {
            return TransportResult::retryable("http-{$status}");
        }
        if ($status >= 500) {
            return TransportResult::retryable("http-{$status}");
        }
        // 4xx: validation/auth/business rejection — permanent, do not retry.
        return TransportResult::permanent("http-{$status}");
    }
}
