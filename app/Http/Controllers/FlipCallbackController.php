<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Services\BillingPaymentService;
use App\Services\Payments\FlipPaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Phase E.1 — Flip Accept Payment V2 callback endpoint.
 *
 * Flip sends a POST application/x-www-form-urlencoded callback with fields:
 *   data  — JSON string (same shape as the create-bill response)
 *   token — Flip dashboard Accept-Payment validation token
 *
 * AUTHENTICITY: the `token` is validated with a constant-time comparison
 * against FLIP_CALLBACK_TOKEN (environment). NO HMAC is used for Flip.
 *
 * RETRY CONTRACT: Flip expects HTTP 200 within ~30s and retries up to 5× at
 * ~2-minute intervals otherwise. Therefore this handler is FAST and performs
 * only source-side settlement (StudentBill PAID + attempt + outbox). It does
 * NOT call Finance synchronously, never calls external APIs, and never runs
 * expensive work. The outbox dispatcher delivers to Finance asynchronously.
 *
 * Failure handling: an invalid token / malformed payload / unknown reference /
 * amount mismatch / non-success status NEVER settles the bill and ALWAYS
 * returns HTTP 200 so Flip does not keep retrying a legitimately-rejected
 * callback. Only a verified SUCCESSFUL callback settles the bill.
 */
class FlipCallbackController extends Controller
{
    public function __construct(
        private readonly BillingPaymentService $billingService,
        private readonly FlipPaymentProvider   $flip,
    ) {}

    public function handle(Request $request)
    {
        // ── 1. Transport authenticity: callback token (constant-time) ────────
        $incomingToken = $request->input('token');
        if (! $this->flip->verifyCallbackToken(is_string($incomingToken) ? $incomingToken : null)) {
            Log::warning('FlipCallback: invalid callback token', ['ip' => $request->ip()]);
            // Return 200 — Flip should not retry a permanently-rejected callback.
            return response()->json(['message' => 'OK'], 200);
        }

        // ── 2. Parse the `data` JSON payload ──────────────────────────────────
        $raw = $request->input('data');
        if (! is_string($raw) || $raw === '') {
            Log::warning('FlipCallback: missing data field', ['ip' => $request->ip()]);
            return response()->json(['message' => 'OK'], 200);
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            Log::warning('FlipCallback: malformed data JSON', ['ip' => $request->ip()]);
            return response()->json(['message' => 'OK'], 200);
        }

        // ── 3. Extract & validate required fields ────────────────────────────




        // Verified sandbox uses `link_id`; accept `bill_link_id` defensively.
        // Either way it is kept as a STRING (never cast to int).
        $rawRef = $data['link_id'] ?? $data['bill_link_id'] ?? null;
        $linkId = $rawRef === null ? null : (string) $rawRef;
        $status = isset($data['status']) ? strtoupper((string) $data['status']) : null;
        $amountRaw = $data['amount'] ?? null;



        if ($linkId === null || $linkId === '' || $status === null) {
            Log::warning('FlipCallback: missing link_id/status', ['ip' => $request->ip()]);
            return response()->json(['message' => 'OK'], 200);
        }

        // ── 4. Resolve the local PaymentAttempt by provider reference ────────
        $attempt = PaymentAttempt::where('provider', PaymentAttempt::PROVIDER_FLIP)

            ->where('provider_reference', $linkId)
            ->first();

        if (! $attempt) {
            // Unknown reference — never settle. Reject safely (no mutation).

            Log::warning('FlipCallback: unknown link_id', ['ip' => $request->ip()]);
            return response()->json(['message' => 'OK'], 200);
        }

        $bill = $attempt->bill;
        if (! $bill) {
            return response()->json(['message' => 'OK'], 200);
        }

        // ── 5. Amount validation (exact integer IDR, no floats) ──────────────
        // Flip amount is integer IDR; StudentBill.amount is authoritative.
        $expected = (int) round((float) $bill->amount);
        $actual   = is_numeric($amountRaw) ? (int) $amountRaw : null;
        if ($actual === null || $actual !== $expected) {
            Log::warning('FlipCallback: amount mismatch — rejected', [
                'bill_id' => $bill->id,
                // Never log the raw provider payload; only the local bill id.
            ]);
            return response()->json(['message' => 'OK'], 200); // no settlement, no outbox
        }

        // ── 6. Status handling — only SUCCESSFUL settles ─────────────────────
        $canonical = $this->flip->mapStatus($status);

        if ($canonical === 'success') {
            // Verified success → atomic source-side settlement (idempotent).
            $channel = isset($data['sender_bank']) ? (string) $data['sender_bank'] : null;
            $this->billingService->handleFlipSuccess($attempt, $status, $channel);
            return response()->json(['message' => 'OK'], 200);
        }

        if ($canonical === 'failed' && $attempt->hasOpenProviderSession()) {
            // Terminal non-settling state → close the attempt (no PAID, no outbox).
            $attempt->markFlipFailed($status);
            Log::info('FlipCallback: attempt closed (failed/cancelled/expired)', [
                'bill_id' => $bill->id,
            ]);
        }
        // 'pending' → nothing to do; leave the attempt open.

        return response()->json(['message' => 'OK'], 200);
    }
}
