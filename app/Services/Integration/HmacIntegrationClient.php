<?php

namespace App\Services\Integration;

use Illuminate\Support\Facades\Http;

/**
 * TU-FINANCE-BATCH-05A — TU-side HMAC request→response client.
 *
 * Complements HmacIntegrationTransport (which is one-way fire-and-forget for
 * outbox delivery and discards the response body). Reconciliation needs the
 * Finance response, so this client returns it. It reuses the SAME HMAC signing
 * spec, headers, and config as the existing transport (single source of truth
 * is docs/TU-FINANCE-HMAC-SPEC.md, mirrored by Finance's TuHmacVerifier):
 *
 *   canonical = {timestamp}\n{METHOD}\n{path}\n{sha256_hex(raw_body)}
 *   signature = base64( HMAC-SHA256( secret, canonical ) )
 *   headers   = X-TU-Key-Id / X-TU-Timestamp / X-TU-Signature
 *
 * This is a thin transport primitive — it carries no business logic and never
 * logs secrets. It does NOT create outbox events or accounting; it only sends
 * a signed body and returns a normalized outcome.
 */
class HmacIntegrationClient
{
    /** Outcome kinds — distinguish transport failure from a real Finance answer. */
    public const OK                  = 'OK';                  // Finance answered (2xx) — body available
    public const AUTH_FAILED         = 'AUTH_FAILED';         // 401 — HMAC rejected
    public const CONTRACT_REJECTED   = 'CONTRACT_REJECTED';   // 4xx (non-401) — envelope/version/validation
    public const UNAVAILABLE         = 'UNAVAILABLE';         // timeout / network / 5xx / 408 / 429
    public const NOT_CONFIGURED      = 'NOT_CONFIGURED';      // no url/secret configured

    /**
     * Send a signed POST and return a normalized outcome.
     *
     * @return array{outcome:string, status:int, body:?array, code:?string}
     */
    public function post(string $urlPath, array $body): array
    {
        $config  = config('integration.finance', []);
        $baseUrl = $config['url'] ?? null;
        $secret  = $config['secret'] ?? null;
        $keyId   = $config['key_id'] ?? 'tu-local';
        $timeout = (int) ($config['timeout'] ?? 10);

        if (! $baseUrl || ! $secret) {
            return $this->outcome(self::NOT_CONFIGURED, 0, null, 'transport-not-configured');
        }

        // Derive scheme+host from the configured finance URL and apply the given path.
        $parts = parse_url($baseUrl);
        $root  = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost')
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $path = '/' . ltrim($urlPath, '/');
        $url  = $root . $path;

        $rawBody   = json_encode($body, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
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
            return $this->outcome(self::UNAVAILABLE, 0, null, 'network-error');
        }

        $status = $response->status();
        $json   = $response->json();
        $body   = is_array($json) ? $json : null;

        if ($status >= 200 && $status < 300) {
            return $this->outcome(self::OK, $status, $body, null);
        }
        if ($status === 401) {
            return $this->outcome(self::AUTH_FAILED, $status, $body, $body['code'] ?? 'INVALID_SIGNATURE');
        }
        if ($status === 408 || $status === 429 || $status >= 500) {
            return $this->outcome(self::UNAVAILABLE, $status, $body, "http-{$status}");
        }
        // Other 4xx — contract/validation/business rejection.
        return $this->outcome(self::CONTRACT_REJECTED, $status, $body, $body['code'] ?? "http-{$status}");
    }

    /** Canonical string — must match Finance's TuHmacVerifier. */
    public function canonicalString(string $timestamp, string $method, string $path, string $rawBody): string
    {
        return $timestamp . "\n" . strtoupper($method) . "\n" . $path . "\n" . hash('sha256', $rawBody);
    }

    protected function sign(string $secret, string $timestamp, string $method, string $path, string $rawBody): string
    {
        return base64_encode(hash_hmac('sha256', $this->canonicalString($timestamp, $method, $path, $rawBody), $secret, true));
    }

    protected function outcome(string $outcome, int $status, ?array $body, ?string $code): array
    {
        return ['outcome' => $outcome, 'status' => $status, 'body' => $body, 'code' => $code];
    }
}
