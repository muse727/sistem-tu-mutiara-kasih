<?php

namespace App\Services\Integration;

/**
 * TU-STUDENT-READ-CONTRACT — TU-side HMAC request VERIFIER.
 *
 * Verifies inbound machine-to-machine requests using the exact same canonical
 * spec as docs/TU-FINANCE-HMAC-SPEC.md (implemented by TU's
 * HmacIntegrationTransport/HmacIntegrationClient as signer, and mirrored by
 * Finance's TuHmacVerifier as verifier):
 *
 *   canonical = {timestamp}\n{METHOD}\n{path}\n{sha256_hex(raw_body)}
 *   signature = base64( HMAC-SHA256( secret, canonical ) )
 *   headers   = X-TU-Key-Id / X-TU-Timestamp / X-TU-Signature
 *
 * HMAC is computed over the EXACT raw request body bytes — never parsed or
 * re-serialized JSON (GET requests carry an empty body; its SHA-256 is the
 * well-known empty-string digest). {path} is the request path only, no query.
 * Comparison is constant-time (hash_equals). Secrets, signatures, and auth
 * headers are never logged or returned.
 *
 * Replay protection (same as the certified scheme): timestamp is part of the
 * signed canonical string, and only requests within the ±300s window verify.
 * Endpoints behind this verifier are READ-ONLY, so an in-window replay can
 * only re-read data — it has no second business effect.
 */
class HmacRequestVerifier
{
    public const HEADER_SIGNATURE = 'X-TU-Signature';

    public const HEADER_TIMESTAMP = 'X-TU-Timestamp';

    public const HEADER_KEY_ID = 'X-TU-Key-Id';

    /** Timestamp tolerance window (seconds) — same as the certified scheme. */
    public const TOLERANCE = 300;

    public const ERR_INVALID_SIGNATURE = 'INVALID_SIGNATURE';

    public const ERR_INVALID_TIMESTAMP = 'INVALID_REQUEST_TIMESTAMP';

    public const ERR_UNKNOWN_KEY = 'UNKNOWN_KEY_ID';

    /** Shared secret for the given key-id (env-provided; never committed). */
    protected function secret(string $keyId): ?string
    {
        $secrets = config('integration.finance_hmac_keys', []);

        return $secrets[$keyId] ?? null;
    }

    /** Build the canonical signing string. */
    public function canonicalString(string $timestamp, string $method, string $path, string $rawBody): string
    {
        return $timestamp."\n".strtoupper($method)."\n".$path."\n".hash('sha256', $rawBody);
    }

    /** Compute the expected signature (base64) for given inputs + secret. */
    public function sign(string $secret, string $timestamp, string $method, string $path, string $rawBody): string
    {
        return base64_encode(hash_hmac('sha256', $this->canonicalString($timestamp, $method, $path, $rawBody), $secret, true));
    }

    /**
     * Verify a request. Returns ['ok'=>bool,'code'=>string].
     * Verification order (same as spec §12):
     *   headers present → key-id → timestamp window → signature (constant-time).
     */
    public function verify(?string $keyId, ?string $timestamp, ?string $signature, string $method, string $path, string $rawBody): array
    {
        if (! $keyId || ! $timestamp || ! $signature) {
            return ['ok' => false, 'code' => self::ERR_INVALID_SIGNATURE];
        }

        $secret = $this->secret($keyId);
        if ($secret === null) {
            return ['ok' => false, 'code' => self::ERR_UNKNOWN_KEY];
        }

        if (! $this->timestampWithinTolerance($timestamp)) {
            return ['ok' => false, 'code' => self::ERR_INVALID_TIMESTAMP];
        }

        $expected = $this->sign($secret, $timestamp, $method, $path, $rawBody);
        // Constant-time comparison; never use == on signatures.
        if (! hash_equals($expected, $signature)) {
            return ['ok' => false, 'code' => self::ERR_INVALID_SIGNATURE];
        }

        return ['ok' => true, 'code' => 'OK'];
    }

    protected function timestampWithinTolerance(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }
        $ts = (int) $timestamp;

        return abs(time() - $ts) <= self::TOLERANCE;
    }
}
