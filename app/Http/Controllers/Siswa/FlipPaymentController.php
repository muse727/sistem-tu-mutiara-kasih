<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use App\Models\StudentBill;
use App\Services\Payments\FlipPaymentProvider;
use App\Services\Payments\ProviderException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Phase E.1 — Create a Flip Accept Payment V2 hosted bill for a StudentBill.
 *
 * Returns a provider-neutral payload: payment_url + payment_reference. The
 * frontend redirects the customer to payment_url (Flip-hosted payment page).
 * The Flip secret is NEVER exposed to the browser.
 *
 * IDEMPOTENCY: exactly ONE open PaymentAttempt per StudentBill. The
 * lock-check-create happens in ONE atomic transaction so no concurrent request
 * can create a duplicate. Flip V2 has NO verified provider idempotency-key —
 * we do NOT claim provider-level idempotency; we protect locally.
 *
 * StudentBill.amount is the authoritative amount — we NEVER accept a frontend
 * amount. Successful creation does NOT mark the bill PAID.
 */
class FlipPaymentController extends Controller
{
    public function __construct(private readonly FlipPaymentProvider $flip) {}

    /**
     * Create (or reuse) a Flip hosted bill for one tagihan.
     * POST /siswa/tagihan/{bill}/pay-flip
     */
    public function createBill(Request $request, StudentBill $bill)
    {
        $student = Auth::guard('siswa')->user();

        if ((int) $bill->student_id !== (int) $student->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($bill->status === 'PAID') {
            return response()->json(['error' => 'Tagihan ini sudah dibayar.'], 400);
        }

        // ── 1. Atomic one-open-attempt guard ─────────────────────────────────
        // Lock the bill row, then check for an existing open Flip attempt.
        // A reusable open attempt returns its existing payment_url (no duplicate).
        $existing = null;
        DB::transaction(function () use ($bill, &$existing) {
            StudentBill::lockForUpdate()->find($bill->id);

            $existing = PaymentAttempt::where('student_bill_id', $bill->id)
                ->where('provider', PaymentAttempt::PROVIDER_FLIP)
                ->where('status', PaymentAttempt::STATUS_PENDING)
                ->whereNotNull('provider_reference')
                ->latest('initiated_at')
                ->first();

            if ($existing && ! $existing->hasOpenProviderSession()) {
                $existing = null; // provider says it's closed → allow a fresh one
            }
        });

        if ($existing) {
            return $this->respond($existing);
        }

        // ── 2. Create the Flip bill via the provider adapter ─────────────────
        // Verified sandbox contract (E0.2): step=2 (payment-method) requires
        // sender_name AND sender_email; step=1 (input step) requires neither.
        // We only send sender fields we actually have — we never invent them.
        $amountIdr = (int) round((float) $bill->amount); // authoritative IDR integer
        $title     = (string) ($bill->name ?? ('Tagihan #' . $bill->id));

        $senderName  = trim((string) ($student->name ?? ''));
        $senderEmail = trim((string) ($student->email ?? ''));

        // Choose a step we can actually satisfy with real data.
        $options = [
            'type' => 'SINGLE',
        ];
        // redirect_url is OPTIONAL. Flip validates the host and rejects URLs it
        // cannot publicly reach/verify — notably Cloudflare quick-tunnel hosts
        // (trycloudflare.com), which this local sandbox uses (verified: same URL
        // accepted when redirect_url is omitted, rejected when passed). Sending an
        // invalid redirect_url makes Flip reject the whole create (422/1059), so we
        // only include it when it is a publicly-valid production URL. When omitted,
        // Flip uses the bill's default post-payment page.
        $redirectUrl = route('siswa.payment.success');
        $host = (string) (parse_url($redirectUrl, PHP_URL_HOST) ?? '');
        if ($host !== '' && ! str_ends_with($host, 'trycloudflare.com') && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
            $options['redirect_url'] = $redirectUrl;
        }
        if ($senderName !== '' && $senderEmail !== '') {
            $options['step']         = 2; // payment-method step (customer picks channel)
            $options['sender_name']  = $senderName;
            $options['sender_email'] = $senderEmail;
        } else {
            $options['step'] = 1; // input step — no sender fields required
        }

        try {






            $providerBill = $this->flip->createBill($amountIdr, $title, $options);
        } catch (ProviderException $e) {
            Log::error('FlipPaymentController: create-bill failed', [
                'bill_id'     => $bill->id,
                'http_status' => $e->httpStatus,
                'code'        => $e->providerCode,
                // NEVER log secret/Authorization/raw token.
            ]);
            return response()->json([
                'error' => 'Gagal membuat pembayaran online. Silakan coba lagi.',
            ], 502);
        }

        // ── 3. Persist the PaymentAttempt (provider reference as STRING) ─────
        $attempt = PaymentAttempt::create([
            'student_bill_id'    => $bill->id,
            'provider'           => PaymentAttempt::PROVIDER_FLIP,
            'provider_reference' => $providerBill->reference, // 19-digit STRING
            'provider_status'    => $providerBill->status,
            'order_id'           => 'FLIP-' . $bill->id . '-' . Str::random(6) . '-' . time(),
            'status'             => PaymentAttempt::STATUS_PENDING,
            'payment_method'     => 'ONLINE',
            'payment_channel'    => null,
            'payment_url'        => $providerBill->paymentUrl,
            'gross_amount'       => $bill->amount,
            'initiated_at'       => now(),
            'source'             => PaymentAttempt::SOURCE_WEB,
        ]);

        return $this->respond($attempt);
    }

    /** Provider-neutral response payload for the frontend. */
    private function respond(PaymentAttempt $attempt)
    {
        return response()->json([
            'provider'          => 'flip',
            'payment_reference' => (string) $attempt->provider_reference,
            'payment_url'       => (string) $attempt->payment_url,
            // Verified provider status at creation is ACTIVE (awaiting payment).
            'status'            => (string) ($attempt->provider_status ?? PaymentAttempt::FLIP_STATUS_PENDING),
        ]);
    }
}
