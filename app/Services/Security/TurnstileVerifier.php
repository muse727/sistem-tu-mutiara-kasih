<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile verification.
 *
 * Single boundary for human-verification checks. Production always performs
 * the real HTTP call to Cloudflare; tests fake the Http facade so no real
 * network call is made. Never bypass verification via this class — the caller
 * decides what to do with the boolean result, this service only reports it.
 */
class TurnstileVerifier
{
    /**
     * Verify a Turnstile token against Cloudflare.
     *
     * @param  string|null  $token     The `cf-turnstile-response` token.
     * @param  string|null  $remoteIp  The end-user IP (optional, recommended).
     */
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (empty($token)) {
            return false;
        }

        $response = Http::asForm()->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'secret'   => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $remoteIp,
            ]
        );

        return (bool) $response->json('success');
    }
}
