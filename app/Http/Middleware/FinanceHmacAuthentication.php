<?php

namespace App\Http\Middleware;

use App\Services\Integration\HmacRequestVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TU-STUDENT-READ-CONTRACT — HMAC machine-to-machine authentication for the
 * Finance-facing read-only integration endpoints. Runs BEFORE any business
 * processing; an invalid HMAC never reaches the controller. Uses the EXACT
 * raw request body for body-hash integrity (GET requests have an empty body).
 * No secrets/signatures/auth headers are logged or leaked. Deterministic
 * 401 errors; no internal detail is exposed.
 */
class FinanceHmacAuthentication
{
    public function __construct(
        protected HmacRequestVerifier $verifier,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $keyId = $request->header(HmacRequestVerifier::HEADER_KEY_ID);
        $timestamp = $request->header(HmacRequestVerifier::HEADER_TIMESTAMP);
        $signature = $request->header(HmacRequestVerifier::HEADER_SIGNATURE);
        $rawBody = $request->getContent(); // exact raw bytes ('' for GET)

        // Signed path component = request path only (no query string).
        $path = '/'.ltrim($request->path(), '/');

        $result = $this->verifier->verify($keyId, $timestamp, $signature, $request->method(), $path, $rawBody);

        if (! $result['ok']) {
            return response()->json(['result' => 'ERROR', 'code' => $result['code']], 401);
        }

        return $next($request);
    }
}
