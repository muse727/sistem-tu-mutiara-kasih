<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentAttempt;
use App\Models\StudentBill;
use App\Services\FinancialAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;

class PaymentSiswaController extends Controller
{
    public function __construct()
    {
        MidtransConfig::$serverKey    = config('services.midtrans.server_key');
        MidtransConfig::$isProduction = config('services.midtrans.is_production', false);
        MidtransConfig::$isSanitized  = config('services.midtrans.is_sanitized', true);
        MidtransConfig::$is3ds        = config('services.midtrans.is_3ds', true);
    }

    /**
     * Buat Midtrans Snap token untuk 1 tagihan.
     * Dipanggil via AJAX dari dashboard siswa.
     */
    public function createToken(Request $request, StudentBill $bill)
    {
        $student = Auth::guard('siswa')->user();

        if ((int) $bill->student_id !== (int) $student->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($bill->status === 'PAID') {
            return response()->json(['error' => 'Tagihan ini sudah dibayar.'], 400);
        }

        // C-04: enforce ONE active pending attempt per bill (per provider).
        // Provider-aware (F-1): only reuse an active MIDTRANS attempt here —
        // a stale FLIP pending attempt must NOT be returned by the Midtrans path.
        // Lock + check + orderId reservation happen in ONE atomic transaction
        // so no concurrent request can slip through the gap between check and create.
        $existingAttempt = null;
        $orderId         = null;
        DB::transaction(function () use ($bill, &$existingAttempt, &$orderId) {
            // Lock bill row for the duration of this transaction.
            StudentBill::lockForUpdate()->find($bill->id);

            $existingAttempt = PaymentAttempt::where('student_bill_id', $bill->id)
                                             ->where('provider', PaymentAttempt::PROVIDER_MIDTRANS)
                                             ->where('status', PaymentAttempt::STATUS_PENDING)
                                             ->latest('initiated_at')
                                             ->first();

            if (! $existingAttempt) {
                // Reserve the order ID inside the lock so no concurrent request
                // can generate an attempt with the same bill while we hold the lock.
                $orderId = 'BILL-' . $bill->id . '-' . Str::random(6) . '-' . time();
                $bill->update(['midtrans_order_id' => $orderId]);
            }
        });

        if ($existingAttempt) {
            return response()->json([
                'snap_token'    => $existingAttempt->snap_token,
                'client_key'    => config('services.midtrans.client_key'),
                'order_id'      => $existingAttempt->order_id,
                'is_production' => config('services.midtrans.is_production', false),
            ]);
        }

        $billName = $bill->name ?: ('Tagihan ' . ($bill->bill_month ?? '') . '/' . ($bill->bill_year ?? ''));

        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $bill->amount,
            ],
            'customer_details' => [
                'first_name' => $student->name,
                'phone'      => $student->parent_phone ?? '',
            ],
            'item_details' => [
                [
                    'id'       => 'bill-' . $bill->id,
                    'price'    => (int) $bill->amount,
                    'quantity' => 1,
                    'name'     => mb_substr($billName, 0, 50), // Midtrans max 50 char
                ],
            ],
            'callbacks' => [
                'finish' => route('siswa.payment.success'),
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);

            // Phase 3.7D: supersede all pending attempts atomically inside
            // the same transaction as the new attempt creation.
            // lockForUpdate() on the bill prevents concurrent initiations from
            // both passing the "no pending attempt" check simultaneously.
            DB::transaction(function () use ($bill, $orderId, $snapToken, $request) {
                StudentBill::lockForUpdate()->find($bill->id);

                // Supersede any pending attempts that survived the pre-check
                // (guards against the narrow race between the pre-check and here).
                $superseded = PaymentAttempt::where('student_bill_id', $bill->id)
                                            ->where('status', PaymentAttempt::STATUS_PENDING)
                                            ->get();

                foreach ($superseded as $old) {
                    $old->update([
                        'status'     => PaymentAttempt::STATUS_CANCEL,
                        'expired_at' => now(),
                    ]);
                    FinancialAuditLogger::paymentAttemptCancelled(
                        $old,
                        AuditLog::SOURCE_WEB,
                        null,
                        $request
                    );
                }

                $attempt = PaymentAttempt::create([
                    'student_bill_id' => $bill->id,
                    'order_id'        => $orderId,
                    'snap_token'      => $snapToken,
                    'status'          => PaymentAttempt::STATUS_PENDING,
                    'gross_amount'    => $bill->amount,
                    'initiated_at'    => now(),
                    'source'          => PaymentAttempt::SOURCE_WEB,
                ]);

                // Maintain backward-compatible payment_token on the bill.
                // payment_token always mirrors the active attempt's snap_token.
                // PaymentAttempt.settled_at = Midtrans settlement_time (provider clock).
                // StudentBill.paid_at       = application processing time (set in callback).
                $bill->update(['payment_token' => $snapToken]);

                FinancialAuditLogger::paymentAttemptCreated(
                    $attempt,
                    AuditLog::SOURCE_WEB,
                    null,
                    $request
                );
            });

            return response()->json([
                'snap_token'    => $snapToken,
                'client_key'    => config('services.midtrans.client_key'),
                'order_id'      => $orderId,
                'is_production' => config('services.midtrans.is_production', false),
            ]);
        } catch (\Exception $e) {
            Log::error('PaymentSiswaController::createToken failed', [
                'bill_id' => $bill->id,
                'error'   => $e->getMessage(),
            ]);
            // F-5: return a generic, user-safe message — never expose the raw
            // exception/provider/SQL detail to the browser. Detail stays in logs.
            return response()->json([
                'error' => 'Gagal membuat pembayaran online. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Buat Midtrans Snap token untuk MULTIPLE tagihan sekaligus.
     * Dipanggil via AJAX dari dashboard siswa (fitur Bayar Dipilih).
     */
    public function createMultiToken(Request $request)
    {
        $student = Auth::guard('siswa')->user();

        $billIds = $request->input('bill_ids', []);

        if (empty($billIds) || !is_array($billIds)) {
            return response()->json(['error' => 'Pilih minimal 1 tagihan.'], 422);
        }

        // Batasi max 10 tagihan sekaligus
        if (count($billIds) > 10) {
            return response()->json(['error' => 'Maksimal 10 tagihan sekaligus.'], 422);
        }

        // Ambil semua bill, verifikasi kepemilikan + status
        $bills = StudentBill::whereIn('id', $billIds)
                            ->where('student_id', $student->id)
                            ->whereIn('status', ['UNPAID', 'PARTIAL'])
                            ->get();

        if ($bills->count() !== count($billIds)) {
            return response()->json(['error' => 'Beberapa tagihan tidak valid atau sudah dibayar.'], 422);
        }

        $grossAmount = $bills->sum('amount');

        if ($grossAmount <= 0) {
            return response()->json(['error' => 'Total tagihan tidak valid.'], 422);
        }

        // Generate 1 order ID untuk semua bill ini
        $orderId = 'MULTI-' . implode('_', $bills->pluck('id')->sort()->values()->toArray())
                   . '-' . Str::random(6) . '-' . time();

        // Build item_details untuk Midtrans
        $itemDetails = $bills->map(function ($bill) {
            $name = $bill->name ?: ('Tagihan ' . ($bill->bill_month ?? '') . '/' . ($bill->bill_year ?? ''));
            return [
                'id'       => 'bill-' . $bill->id,
                'price'    => (int) $bill->amount,
                'quantity' => 1,
                'name'     => mb_substr($name, 0, 50),
            ];
        })->values()->toArray();

        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $student->name,
                'phone'      => $student->parent_phone ?? '',
            ],
            'item_details' => $itemDetails,
            'callbacks' => [
                'finish' => route('siswa.payment.success'),
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);

            // Simpan orderId ke semua bill + buat PaymentAttempt untuk masing-masing
            DB::transaction(function () use ($bills, $orderId, $snapToken, $grossAmount, $request) {
                foreach ($bills as $bill) {
                    // Cancel pending attempts lama
                    $pending = PaymentAttempt::where('student_bill_id', $bill->id)
                                            ->where('status', PaymentAttempt::STATUS_PENDING)
                                            ->get();
                    foreach ($pending as $old) {
                        $old->update(['status' => PaymentAttempt::STATUS_CANCEL, 'expired_at' => now()]);
                    }

                    // Update bill dengan multi order ID
                    $bill->update([
                        'midtrans_order_id' => $orderId,
                        'payment_token'     => $snapToken,
                    ]);

                    // Buat attempt untuk bill ini.
                    PaymentAttempt::create([
                        'student_bill_id' => $bill->id,
                        'order_id'        => $orderId,
                        'snap_token'      => $snapToken,
                        'status'          => PaymentAttempt::STATUS_PENDING,
                        'gross_amount'    => $bill->amount,
                        'initiated_at'    => now(),
                        'source'          => PaymentAttempt::SOURCE_WEB,
                    ]);
                }
            });

            return response()->json([
                'snap_token'    => $snapToken,
                'client_key'    => config('services.midtrans.client_key'),
                'order_id'      => $orderId,
                'is_production' => config('services.midtrans.is_production', false),
                'total'         => $grossAmount,
                'bill_count'    => $bills->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('PaymentSiswaController::createMultiToken failed', [
                'bill_ids' => $billIds,
                'error'    => $e->getMessage(),
            ]);
            // F-5: generic, user-safe message — never expose raw exception detail.
            return response()->json([
                'error' => 'Gagal membuat pembayaran online. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Midtrans server-to-server webhook callback.
     * Route ini di-exclude dari CSRF di bootstrap/app.php.
     *
     * Validation chain (fail-fast, always HTTP 200 on rejection):
     *   1. Server key configured   (fail-closed)
     *   2. Signature valid         (Phase 1.1 — hash_equals SHA512)
     *   3. order_id present        (Phase 1.2-B)
     *   4. Bill exists             (Phase 1.2-B)
     *   5. Amount matches          (Phase 1.2-C)
     *   6. Idempotency             (Phase 1.2-D)
     *   7. Transaction status      (Phase 1.2-E)
     */
    public function callback(Request $request)
    {
        $payload = $request->all();

        // ── PHASE 1.1: Signature Verification ────────────────────────────────
        // Spec: SHA512(order_id + status_code + gross_amount + server_key)
        // Ref:  https://docs.midtrans.com/docs/verifying-data-integrity
        $orderId     = $payload['order_id']     ?? '';
        $statusCode  = $payload['status_code']  ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $serverKey   = config('services.midtrans.server_key');

        // ── PHASE 1.2-A: Fail-Closed Server Key Check ────────────────────────
        // If server key is empty, SHA512(payload + '') could match a crafted
        // request that also used an empty key. Reject before comparing.
        if (empty($serverKey)) {
            Log::error('Midtrans callback: MIDTRANS_SERVER_KEY is not configured', [
                'order_id' => $orderId,
                'ip'       => $request->ip(),
            ]);
            return response()->json(['message' => 'OK'], 200);
        }
        // ── End Fail-Closed Check ─────────────────────────────────────────────

        $expectedSig = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        $receivedSig = $payload['signature_key'] ?? '';

        // hash_equals() prevents timing-based side-channel attacks.
        // Returns HTTP 200 on failure so Midtrans does not retry indefinitely.
        if (! hash_equals($expectedSig, $receivedSig)) {
            Log::warning('Midtrans callback: invalid signature', [
                'order_id'   => $orderId,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return response()->json(['message' => 'OK'], 200);
        }
        // ── End Signature Verification ────────────────────────────────────────

        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus       = $payload['fraud_status']       ?? 'accept';

        // ── PHASE 1.2-B: Order Validation ────────────────────────────────────
        // order_id format: BILL-{bill_id}-{rand6}-{timestamp}
        // Extract bill_id from the order_id string we generated in createToken().
        //
        // NOTE: payment_token stores the Snap token (a different identifier).
        // The canonical link between webhook and bill is the bill_id embedded
        // in the order_id — we do NOT look up by payment_token here.
        if (empty($orderId)) {
            Log::warning('Midtrans callback: missing order_id in payload', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'OK'], 200);
        }

        // Support dua format order_id:
        // - BILL-{id}-{rand}-{ts}   → single bill
        // - MULTI-{id1}_{id2}_...-{rand}-{ts} → multi bill
        $isMulti = str_starts_with($orderId, 'MULTI-');

        if ($isMulti) {
            // Delegate ke handler multi-bill
            return $this->handleMultiCallback($orderId, $transactionStatus, $fraudStatus, $payload, $request);
        }

        preg_match('/^BILL-(\d+)-/', $orderId, $matches);
        $billId = $matches[1] ?? null;

        if (! $billId) {
            Log::warning('Midtrans callback: unrecognised order_id format', [
                'order_id' => $orderId,
                'ip'       => $request->ip(),
            ]);
            return response()->json(['message' => 'OK'], 200);
        }

        $bill = StudentBill::find($billId);

        if (! $bill) {
            Log::warning('Midtrans callback: bill not found for order', [
                'order_id' => $orderId,
                'bill_id'  => $billId,
                'ip'       => $request->ip(),
            ]);
            return response()->json(['message' => 'OK'], 200);
        }
        // ── End Order Validation ──────────────────────────────────────────────

        // ── PHASE 1.2-C: Amount Validation ───────────────────────────────────
        // Cast both sides via round(float) → int to eliminate string/decimal
        // representation drift. e.g. "150000.00" == 150000 == 150000.00
        $receivedAmount = (int) round((float) $grossAmount);
        $expectedAmount = (int) round((float) $bill->amount);

        if ($receivedAmount !== $expectedAmount) {
            Log::error('Midtrans callback: amount mismatch — bill NOT marked PAID', [
                'order_id'        => $orderId,
                'bill_id'         => $bill->id,
                'expected_amount' => $expectedAmount,
                'received_amount' => $receivedAmount,
                'ip'              => $request->ip(),
            ]);
            return response()->json(['message' => 'OK'], 200);
        }
        // ── End Amount Validation ─────────────────────────────────────────────

        // ── PHASE 1.2-D: Idempotency Check ───────────────────────────────────
        // Basis: bill.status + bill.midtrans_order_id
        //
        // Case 1 — already PAID, same order_id:
        //   Duplicate webhook delivery for the same settlement → skip silently.
        //
        // Case 2 — already PAID, different order_id:
        //   Should not happen (one bill = one successful payment), but log as
        //   anomaly and skip — do NOT overwrite existing payment data.
        //
        // Case 3 — bill is UNPAID → fall through to status handling.
        if ($bill->status === 'PAID') {
            if ($bill->midtrans_order_id === $orderId) {
                Log::info('Midtrans callback: duplicate webhook ignored (already PAID)', [
                    'order_id' => $orderId,
                    'bill_id'  => $bill->id,
                ]);
            } else {
                Log::warning('Midtrans callback: bill already PAID with a different order_id', [
                    'order_id'          => $orderId,
                    'bill_id'           => $bill->id,
                    'existing_order_id' => $bill->midtrans_order_id,
                ]);
            }
            return response()->json(['message' => 'OK'], 200);
        }
        // ── End Idempotency Check ─────────────────────────────────────────────

        // ── PHASE 1.2-E: Transaction Status Handling ──────────────────────────
        if (in_array($transactionStatus, ['settlement', 'capture']) && $fraudStatus === 'accept') {

            // Successful payment — persist all fields atomically.
            // confirmed_by is explicitly NULL: Midtrans settlement is confirmed
            // by the payment gateway, not by a TU operator.
            DB::transaction(function () use ($bill, $orderId, $transactionStatus, $request, $payload) {

                // ── Phase 3.7D: attempt-level idempotency ─────────────────────
                // Look up the attempt for this specific order_id.
                // If already terminal: write SETTLEMENT_IGNORED and return early.
                // If pending: update it, then update the bill.
                $attempt = PaymentAttempt::where('order_id', $orderId)->first();

                if ($attempt) {
                    if (in_array($attempt->status, PaymentAttempt::TERMINAL_STATUSES)) {
                        // Attempt already settled/expired/cancelled — do NOT touch bill.
                        // Log as an operational exception (user may have been charged).
                        FinancialAuditLogger::paymentAttemptSettlementIgnored(
                            $attempt,
                            $payload,
                            $request
                        );
                        Log::warning('Midtrans callback: settlement ignored — attempt already terminal', [
                            'order_id'       => $orderId,
                            'attempt_status' => $attempt->status,
                            'bill_id'        => $bill->id,
                        ]);
                        return; // Early return — no bill mutation.
                    }

                    // Attempt is pending — settle it.
                    // PaymentAttempt.settled_at = Midtrans settlement_time (provider clock).
                    // StudentBill.paid_at       = application processing time (set below).
                    $attempt->update([
                        'status'         => $transactionStatus,
                        'payment_method' => $payload['payment_type']              ?? null,
                        'bank'           => $payload['bank']
                                            ?? ($payload['va_numbers'][0]['bank'] ?? null),
                        'va_number'      => $payload['va_numbers'][0]['va_number'] ?? null,
                        'transaction_id' => $payload['transaction_id']            ?? null,
                        'settled_at'     => $this->parseMidtransTimestamp(
                                               $payload['settlement_time'] ?? ($payload['transaction_time'] ?? null)
                                           ) ?? now(),
                        'expired_at'     => null,
                        'snap_token'     => null,
                    ]);
                } else {
                    // Legacy webhook — no PaymentAttempt record exists.
                    // Proceed with bill update only; do not fabricate an attempt.
                    Log::info('Midtrans callback: legacy webhook — no PaymentAttempt found, bill updated directly', [
                        'order_id' => $orderId,
                        'bill_id'  => $bill->id,
                    ]);
                }

                // Canonical gateway source recorded on the bill. The specific
                // payment channel (VA BCA, GoPay, QRIS, ...) is preserved on the
                // PaymentAttempt.payment_method / bank columns, not on the bill —
                // the bill's payment_method uses the canonical value 'MIDTRANS'
                // consistent with BillingPaymentService and ParentApiController.
                $bill->update([
                    'status'            => 'PAID',
                    'paid_at'           => now(),   // application clock — intentionally differs from settled_at
                    'payment_method'    => 'MIDTRANS',
                    'confirmed_by'      => null,    // Phase 2.3: gateway-confirmed, no operator
                    'midtrans_order_id' => $orderId,
                    'payment_token'     => null,
                    'verify_token'      => $bill->verify_token ?? \Illuminate\Support\Str::random(32),
                ]);

                $bill->refresh();
                FinancialAuditLogger::paymentConfirmed(
                    $bill,
                    \App\Models\AuditLog::SOURCE_MIDTRANS,
                    null,
                    $request
                );

                // Phase F-3 — emit the canonical spp.payment Finance outbox event
                // INSIDE the same settlement transaction (atomic with the PAID
                // mutation), consistent with BillingPaymentService::recordIntegrationEvent
                // and the Flip path. KANTIN debt settlement is a TU-domain concern and
                // emits NO Finance integration event (09D.6-FIX). recordSppPayment() is
                // idempotent (UNIQUE on source_system+event_type+source_record_id) and
                // self-gates on integration.enabled / activation boundary.
                if ($bill->type !== 'KANTIN') {
                    app(\App\Services\Integration\IntegrationOutboxService::class)
                        ->recordSppPayment($bill);
                    // TU-FINANCE-BATCH-02 — batch membership (no-op when flag disabled).
                    app(\App\Services\Integration\SppSettlementBatchService::class)
                        ->recordEligibleSettlement($bill);
                }
            });

            Log::info('Midtrans callback: payment settled', [
                'order_id'           => $orderId,
                'bill_id'            => $bill->id,
                'transaction_status' => $transactionStatus,
                'status_code'        => $statusCode,
                'gross_amount'       => $grossAmount,
            ]);

        } elseif (in_array($transactionStatus, ['expire', 'cancel', 'deny'])) {

            // Failed / expired — clear the active Snap token so the student
            // can initiate a fresh payment attempt. Bill stays UNPAID.
            // Phase 3.5: log PAYMENT_FAILED before clearing token (captures original token).
            DB::transaction(function () use ($bill, $orderId, $transactionStatus, $request, $payload) {

                // ── Phase 3.7D: attempt-level state machine ──────────────────
                $attempt = PaymentAttempt::where('order_id', $orderId)->first();

                if ($attempt && ! in_array($attempt->status, PaymentAttempt::TERMINAL_STATUSES)) {
                    $attempt->update([
                        'status'         => $transactionStatus,
                        'payment_method' => $payload['payment_type']              ?? null,
                        'bank'           => $payload['bank']
                                            ?? ($payload['va_numbers'][0]['bank'] ?? null),
                        'va_number'      => $payload['va_numbers'][0]['va_number'] ?? null,
                        'transaction_id' => $payload['transaction_id']            ?? null,
                        'expired_at'     => $this->parseMidtransTimestamp(
                                               $payload['expiry_time'] ?? ($payload['transaction_time'] ?? null)
                                           ) ?? now(),
                        'snap_token'     => null,
                    ]);
                    $attemptWasMutated = true;
                } elseif ($attempt && in_array($attempt->status, PaymentAttempt::TERMINAL_STATUSES)) {
                    // Already terminal — idempotent, no re-write.
                    Log::info('Midtrans callback: expire/cancel ignored — attempt already terminal', [
                        'order_id'       => $orderId,
                        'attempt_status' => $attempt->status,
                    ]);
                    $attemptWasMutated = false;
                } else {
                    Log::info('Midtrans callback: legacy webhook expire/cancel — no PaymentAttempt found', [
                        'order_id' => $orderId,
                        'bill_id'  => $bill->id,
                        'status'   => $transactionStatus,
                    ]);
                    $attemptWasMutated = true; // legacy path — write PAYMENT_FAILED
                }

                // Only clear payment_token if no other pending attempt is active
                // for this bill — prevents wiping a newer session's token.
                $hasOtherPending = PaymentAttempt::where('student_bill_id', $bill->id)
                    ->where('status', PaymentAttempt::STATUS_PENDING)
                    ->where('order_id', '!=', $orderId)
                    ->exists();

                // PAYMENT_FAILED only written if:
                //   a) the attempt was actually mutated (not already terminal), AND
                //   b) the bill is not already PAID.
                if ($attemptWasMutated && $bill->status !== 'PAID') {
                    FinancialAuditLogger::paymentFailed(
                        $bill,
                        $transactionStatus,
                        \App\Models\AuditLog::SOURCE_MIDTRANS,
                        $request
                    );

                    if (! $hasOtherPending) {
                        $bill->update(['payment_token' => null]);
                    }
                }
            });

            Log::info('Midtrans callback: payment not completed', [
                'order_id'           => $orderId,
                'bill_id'            => $bill->id,
                'transaction_status' => $transactionStatus,
            ]);

        } elseif ($transactionStatus === 'pending') {

            // Midtrans is waiting for bank confirmation — no state change.
            // A follow-up webhook will arrive when the status resolves.
            Log::info('Midtrans callback: payment pending, no state change', [
                'order_id' => $orderId,
                'bill_id'  => $bill->id,
            ]);

        } else {

            // Unhandled / unknown status — log and leave bill state unchanged.
            Log::warning('Midtrans callback: unhandled transaction_status', [
                'order_id'           => $orderId,
                'bill_id'            => $bill->id,
                'transaction_status' => $transactionStatus,
                'status_code'        => $statusCode,
            ]);
        }
        // ── End Transaction Status Handling ───────────────────────────────────

        return response()->json(['message' => 'OK'], 200);
    }

    /**
     * Handle Midtrans callback untuk order MULTI- (bayar beberapa tagihan sekaligus).
     * Dipanggil dari callback() saat order_id dideteksi prefix MULTI-.
     */
    private function handleMultiCallback(string $orderId, string $transactionStatus, string $fraudStatus, array $payload, $request)
    {
        // Parse bill IDs dari format: MULTI-{id1}_{id2}_...-{rand}-{ts}
        // Contoh: MULTI-5_7_12-aBcDeF-1788335963
        preg_match('/^MULTI-([\_\d]+)-/', $orderId, $matches);
        $idString = $matches[1] ?? null;

        if (! $idString) {
            Log::warning('Midtrans callback: unrecognised MULTI order_id format', [
                'order_id' => $orderId,
                'ip'       => $request->ip(),
            ]);
            return response()->json(['message' => 'OK'], 200);
        }

        $billIds = array_filter(explode('_', $idString), 'is_numeric');

        if (empty($billIds)) {
            Log::warning('Midtrans callback: no valid bill IDs in MULTI order_id', [
                'order_id' => $orderId,
            ]);
            return response()->json(['message' => 'OK'], 200);
        }

        $bills = StudentBill::whereIn('id', $billIds)->get();

        if ($bills->isEmpty()) {
            Log::warning('Midtrans callback: no bills found for MULTI order', [
                'order_id' => $orderId,
                'bill_ids' => $billIds,
            ]);
            return response()->json(['message' => 'OK'], 200);
        }

        // ── Amount integrity (Phase 09B.2) ───────────────────────────────────
        // The provider gross_amount must equal the SUM of the authoritative
        // bill amounts for this MULTI order. A callback amount must never be
        // trusted merely because the order_id exists. Mismatch → reject, no
        // financial state is mutated. (Mirrors the single-bill amount guard.)
        $receivedAmount = (int) round((float) ($payload['gross_amount'] ?? 0));
        $expectedAmount = (int) round((float) $bills->sum('amount'));

        if ($receivedAmount !== $expectedAmount) {
            Log::error('Midtrans callback: MULTI amount mismatch — bills NOT marked PAID', [
                'order_id'        => $orderId,
                'bill_ids'        => $bills->pluck('id')->all(),
                'expected_amount' => $expectedAmount,
                'received_amount' => $receivedAmount,
                'ip'              => $request->ip(),
            ]);
            return response()->json(['message' => 'OK'], 200);
        }
        // ── End Amount integrity ─────────────────────────────────────────────

        if (in_array($transactionStatus, ['settlement', 'capture']) && $fraudStatus === 'accept') {

            DB::transaction(function () use ($bills, $orderId, $transactionStatus, $payload, $request) {
                foreach ($bills as $bill) {
                    if ($bill->status === 'PAID') {
                        continue; // Idempotent — skip jika sudah paid
                    }

                    // Settle attempt untuk bill ini
                    $attempt = PaymentAttempt::where('student_bill_id', $bill->id)
                                            ->where('order_id', $orderId)
                                            ->first();

                    if ($attempt && ! in_array($attempt->status, PaymentAttempt::TERMINAL_STATUSES)) {
                        $attempt->update([
                            'status'         => $transactionStatus,
                            'payment_method' => $payload['payment_type'] ?? null,
                            'bank'           => $payload['bank'] ?? ($payload['va_numbers'][0]['bank'] ?? null),
                            'va_number'      => $payload['va_numbers'][0]['va_number'] ?? null,
                            'transaction_id' => $payload['transaction_id'] ?? null,
                            'settled_at'     => $this->parseMidtransTimestamp(
                                                   $payload['settlement_time'] ?? ($payload['transaction_time'] ?? null)
                                               ) ?? now(),
                            'expired_at'     => null,
                            'snap_token'     => null,
                        ]);
                    }

                    $bill->update([
                        'status'            => 'PAID',
                        'paid_at'           => now(),
                        'payment_method'    => 'MIDTRANS',
                        'confirmed_by'      => null,
                        'midtrans_order_id' => $orderId,
                        'payment_token'     => null,
                        'verify_token'      => $bill->verify_token ?? \Illuminate\Support\Str::random(32),
                    ]);

                    // MULTI-02 — emit the canonical spp.payment Finance outbox event
                    // per settled bill, INSIDE the same MULTI settlement transaction
                    // (atomic with the PAID mutation), mirroring the single-bill path
                    // (Phase F-3) and BillingPaymentService::recordIntegrationEvent.
                    // Each event carries THAT bill's own authoritative amount — never
                    // the shared MULTI total. KANTIN debt settlement is a TU-domain
                    // concern and emits NO Finance integration event (09D.6-FIX).
                    // recordSppPayment() is idempotent (UNIQUE on
                    // source_system+event_type+source_record_id → TU:spp.payment:{bill_id})
                    // and self-gates on integration.enabled / activation boundary, so a
                    // duplicate/replayed MULTI callback cannot create a second event.
                    if ($bill->type !== 'KANTIN') {
                        app(\App\Services\Integration\IntegrationOutboxService::class)
                            ->recordSppPayment($bill);
                        // TU-FINANCE-BATCH-02 — batch membership (no-op when flag disabled).
                        app(\App\Services\Integration\SppSettlementBatchService::class)
                            ->recordEligibleSettlement($bill);
                    }

                    Log::info('Midtrans callback: MULTI bill settled', [
                        'order_id' => $orderId,
                        'bill_id'  => $bill->id,
                    ]);
                }
            });

            Log::info('Midtrans callback: MULTI payment settled', [
                'order_id'  => $orderId,
                'bill_count' => $bills->count(),
            ]);

        } elseif (in_array($transactionStatus, ['expire', 'cancel', 'deny'])) {
            // Cancel semua pending attempts untuk order ini
            DB::transaction(function () use ($bills, $orderId, $transactionStatus, $request, $payload) {
                foreach ($bills as $bill) {
                    $attempt = PaymentAttempt::where('student_bill_id', $bill->id)
                                            ->where('order_id', $orderId)
                                            ->where('status', PaymentAttempt::STATUS_PENDING)
                                            ->first();

                    if ($attempt) {
                        $attempt->update([
                            'status'     => PaymentAttempt::STATUS_CANCEL,
                            'expired_at' => now(),
                            'snap_token' => null,
                        ]);
                    }

                    // Clear payment token jika masih aktif untuk order ini
                    if ($bill->midtrans_order_id === $orderId) {
                        $bill->update(['payment_token' => null]);
                    }
                }
            });

            Log::info('Midtrans callback: MULTI payment expired/cancelled', [
                'order_id'           => $orderId,
                'transaction_status' => $transactionStatus,
            ]);
        }

        return response()->json(['message' => 'OK'], 200);
    }

    /**
     * Resolve a human-readable payment method label from Midtrans payload fields.
     *
     * payment_type values from Midtrans:
     *   bank_transfer, gopay, qris, shopeepay, cstore, akulaku, kredivo, uob_ezpay
     * bank values: bca, bni, bri, mandiri, permata
     */
    private static function resolvePaymentMethodLabel(?string $paymentType, ?string $bank): string
    {
        if (empty($paymentType)) {
            return 'Midtrans';
        }

        $bank = strtoupper($bank ?? '');

        return match ($paymentType) {
            'bank_transfer'  => $bank ? "VA {$bank}" : 'Virtual Account',
            'echannel'       => 'Mandiri Bill',
            'gopay'          => 'GoPay',
            'dana'           => 'Dana',
            'ovo'            => 'OVO',
            'linkaja'        => 'LinkAja',
            'qris'           => 'QRIS',
            'shopeepay'      => 'ShopeePay',
            'akulaku'        => 'Akulaku',
            'kredivo'        => 'Kredivo',
            'cstore'         => $bank === 'ALFAMART' ? 'Alfamart' : ($bank === 'INDOMARET' ? 'Indomaret' : 'Minimarket'),
            'uob_ezpay'      => 'UOB EZPay',
            default          => ucwords(str_replace('_', ' ', $paymentType)),
        };
    }

    /**
     * Parse a Midtrans timestamp string into a Carbon instance.
     * Midtrans uses "YYYY-MM-DD HH:MM:SS" in Asia/Jakarta timezone.
     * Returns null if the value is empty or unparseable.
     */
    private function parseMidtransTimestamp(?string $value): ?\Carbon\Carbon
    {
        if (empty($value)) {
            return null;
        }
        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $value, 'Asia/Jakarta')
                                  ->setTimezone(config('app.timezone', 'Asia/Jakarta'));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Halaman struk/bukti pembayaran.
     *
     * PHASE 1.4 — IDOR fix: ownership enforced at the query level.
     * Bill is resolved only if it belongs to the authenticated student.
     * A bill ID belonging to another student returns 404, which does not
     * confirm to the requester whether that bill ID exists at all.
     *
     * Approach: query-scoped lookup (StudentBill::where('student_id')->findOrFail)
     * replaces implicit model binding + post-resolution ownership check.
     */
    public function struk(int $id)
    {
        $student = Auth::guard('siswa')->user();

        // Scoped lookup — only resolves if student_id matches the authenticated
        // student. findOrFail() throws ModelNotFoundException (→ 404) on any
        // mismatch or non-existent ID. No bill data is ever loaded for a
        // request that does not belong to this student.
        $bill = StudentBill::where('student_id', $student->id)
                           ->findOrFail($id);

        if ($bill->status !== 'PAID') {
            return redirect()->route('siswa.dashboard');
        }

        $schoolName    = DB::table('school_settings')->where('key', 'school_name')->value('value') ?? 'Sekolah';
        $schoolAddress = DB::table('school_settings')->where('key', 'school_address')->value('value') ?? '';

        return view('siswa.struk', compact('bill', 'student', 'schoolName', 'schoolAddress'));
    }

    /**
     * Halaman redirect setelah Midtrans Snap selesai.
     */
    public function success(Request $request)
    {
        return redirect()->route('siswa.dashboard')
                         ->with('success', 'Pembayaran berhasil diproses! Tagihan akan diperbarui setelah konfirmasi dari bank.');
    }
}
