<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PaymentAttempt;
use App\Models\StudentBill;
use App\Services\Integration\IntegrationOutboxService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 8.4 — BillingPaymentService
 *
 * Centralises all payment state transitions for StudentBill.
 * Controllers delegate to this service — they handle only request
 * binding and HTTP responses.
 *
 * Responsibilities:
 *   - Validate that a bill is in a payable state before writing.
 *   - Transition bill status atomically inside a DB transaction.
 *   - Deduct POS stock when a bundle bill is paid.
 *   - Write a FinancialAuditLogger entry within the same transaction.
 *
 * Design rules:
 *   - Throws \RuntimeException for business-rule violations (already PAID, etc.)
 *     so the caller can catch and convert to the appropriate HTTP response.
 *   - Never returns a raw boolean — either succeeds or throws.
 */
class BillingPaymentService
{
    // ─────────────────────────────────────────────────────────────────────────
    // 1. payCash — manual cash payment at TU counter
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mark a bill as paid via cash.
     *
     * @throws \RuntimeException if the bill is already PAID.
     */
    public function payCash(StudentBill $bill, ?int $confirmedBy = null): StudentBill
    {
        $this->validatePaymentState($bill);

        DB::transaction(function () use ($bill, $confirmedBy) {
            // Deduct POS stock for bundle items
            $this->deductBundleStock($bill);

            $bill->update([
                'status'         => 'PAID',
                'paid_at'        => now(),
                'payment_method' => 'CASH',
                'confirmed_by'   => $confirmedBy ?? Auth::id(),
            ]);

            FinancialAuditLogger::paymentConfirmed(
                $bill,
                AuditLog::SOURCE_WEB,
                $confirmedBy ?? Auth::id()
            );

            // Phase 09D.2 — atomic outbox event in the SAME transaction.
            $this->recordIntegrationEvent($bill);
        });

        return $bill->refresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. handleMidtransSuccess — called by Midtrans webhook callback
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Settle a bill after a successful Midtrans payment.
     * Idempotent — silently returns if the bill is already PAID.
     *
     * @param  string $orderId  Midtrans order_id (e.g. "BILL-{id}-{token}")
     * @return StudentBill|null  null if no matching bill found
     */
    public function handleMidtransSuccess(string $orderId): ?StudentBill
    {
        $bill = StudentBill::where('midtrans_order_id', $orderId)->first();

        if (! $bill) {
            Log::warning('BillingPaymentService: no bill found for Midtrans order', [
                'order_id' => $orderId,
            ]);
            return null;
        }

        // Idempotency guard — Midtrans may send duplicate settlement callbacks
        if ($bill->status === 'PAID') {
            Log::info('BillingPaymentService: duplicate Midtrans callback ignored (already PAID)', [
                'bill_id'  => $bill->id,
                'order_id' => $orderId,
            ]);
            return $bill;
        }

        DB::transaction(function () use ($bill, $orderId) {
            $this->deductBundleStock($bill);

            $bill->update([
                'status'         => 'PAID',
                'paid_at'        => now(),
                'payment_method' => 'MIDTRANS',
                'payment_token'  => null, // clear token after settlement
            ]);

            FinancialAuditLogger::paymentConfirmed(
                $bill,
                AuditLog::SOURCE_MIDTRANS,
                null // no user — automated callback
            );

            // Phase 09D.2 — atomic outbox event in the SAME transaction.
            $this->recordIntegrationEvent($bill);
        });

        return $bill->refresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2b. handleFlipSuccess — called by Flip Accept Payment V2 callback
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Settle a bill after a verified Flip SUCCESSFUL callback.
     *
     * The CALLBACK CONTROLLER is responsible for transport authenticity (token),
     * payload parsing, amount validation and status validation BEFORE calling
     * this method. This method performs the atomic source-side settlement:
     *   StudentBill → PAID  +  PaymentAttempt settled  +  outbox event,
     * all inside ONE DB transaction (outbox is atomic with the PAID mutation).
     *
     * Idempotent: if the bill is already PAID, this is a no-op (duplicate
     * callback) — no second settlement, no second outbox, no paid_at overwrite.
     *
     * @param  PaymentAttempt $attempt        The resolved pending attempt.
     * @param  string         $providerStatus Raw Flip status (e.g. SUCCESSFUL).
     * @param  string|null    $paymentChannel Channel if returned by Flip.
     * @return StudentBill|null  null if no bill is linked (should not happen).
     */
    public function handleFlipSuccess(
        PaymentAttempt $attempt,
        string $providerStatus = PaymentAttempt::FLIP_STATUS_SUCCESSFUL,
        ?string $paymentChannel = null
    ): ?StudentBill {
        $bill = $attempt->bill;

        if (! $bill) {
            Log::warning('BillingPaymentService: Flip attempt has no bill', [
                'attempt_id' => $attempt->id,
            ]);
            return null;
        }

        // Idempotency guard — Flip retries callbacks; already-PAID is a no-op.
        if ($bill->status === 'PAID') {
            Log::info('BillingPaymentService: duplicate Flip callback ignored (already PAID)', [
                'bill_id'    => $bill->id,
                'attempt_id' => $attempt->id,
            ]);
            return $bill;
        }

        DB::transaction(function () use ($bill, $attempt, $providerStatus, $paymentChannel) {
            $this->deductBundleStock($bill);

            // Settle the attempt ledger (provider status recorded verbatim).
            $attempt->markFlipSettled($providerStatus, $paymentChannel);

            $bill->update([
                'status'         => 'PAID',
                'paid_at'        => now(),
                'payment_method' => 'ONLINE', // canonical: ONLINE + provider FLIP
                'payment_token'  => null,
            ]);

            FinancialAuditLogger::paymentConfirmed(
                $bill,
                AuditLog::SOURCE_SYSTEM,
                null // no user — automated callback
            );

            // Atomic outbox event in the SAME transaction.
            $this->recordIntegrationEvent($bill);
        });

        return $bill->refresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. validatePaymentState — shared guard
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assert that a bill can accept a payment.
     *
     * @throws \RuntimeException if the bill is already PAID or CANCELLED.
     */
    /**
     * Phase 09D.2 — record the TU Finance Contract v1 outbox event for a settled
     * bill, INSIDE the settlement transaction (atomic with the PAID mutation).
     * Idempotent: the canonical unique constraint ensures exactly one event per
     * settled fact.
     *
     * Phase 09D.6-FIX — only spp.payment is a supported SPP/bill integration
     * event. pos.kantin_payment is OUT OF SCOPE (the product has no KANTIN
     * Finance integration module); KANTIN debt bills settle normally but emit
     * NO Finance integration event. All non-KANTIN bill types → spp.payment.
     */
    protected function recordIntegrationEvent(StudentBill $bill): void
    {
        if ($bill->type === 'KANTIN') {
            return; // KANTIN debt settlement is a TU-domain concern, not a Finance integration event (09D.6-FIX)
        }
        app(IntegrationOutboxService::class)->recordSppPayment($bill);
    }

    public function validatePaymentState(StudentBill $bill): void
    {
        if ($bill->status === 'PAID') {
            throw new \RuntimeException('Tagihan ini sudah lunas.');
        }

        if ($bill->status === 'CANCELLED') {
            throw new \RuntimeException('Tagihan ini sudah dibatalkan dan tidak dapat dibayar.');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Deduct POS stock for each BillItem that links to a PosItem.
     * Only runs for PAKET-type bills with items.
     * Safe to call for SPP/LAINNYA bills — no-op if no items.
     */
    private function deductBundleStock(StudentBill $bill): void
    {
        // Opsi A/C: Untuk tagihan PAKET dan KANTIN, stok sudah dipotong
        // saat generate bills / checkout kasir. Skip agar tidak double-potong.
        if (in_array($bill->type, ['PAKET', 'KANTIN'])) {
            return;
        }

        if ($bill->relationLoaded('items') === false) {
            $bill->load('items.product');
        }

        // Phase 8.4: validate stock before any decrement so the transaction
        // rolls back atomically if any item is out of stock.
        // Resolves the PosItem via direct pos_item_id first, then via pos_bundle_items.
        foreach ($bill->items as $item) {
            $posItem  = $item->product; // direct link
            $required = $item->quantity;

            if (! $posItem && $item->pos_bundle_id) {
                // Resolve via bundle — find the bundled PosItem row
                $bundleItem = \DB::table('pos_bundle_items')
                    ->where('pos_bundle_id', $item->pos_bundle_id)
                    ->first();

                if ($bundleItem) {
                    $posItem  = \App\Models\PosItem::find($bundleItem->pos_item_id);
                    $required = $item->quantity * $bundleItem->quantity;
                }
            }

            if ($posItem && $posItem->stock < $required) {
                throw new \RuntimeException(
                    "Stok {$posItem->name} tidak mencukupi. "
                    . "Tersedia: {$posItem->stock}, dibutuhkan: {$required}."
                );
            }
        }

        // All stock checks passed — now perform the decrements atomically
        foreach ($bill->items as $item) {
            $posItem  = $item->product;
            $required = $item->quantity;

            if (! $posItem && $item->pos_bundle_id) {
                $bundleItem = \DB::table('pos_bundle_items')
                    ->where('pos_bundle_id', $item->pos_bundle_id)
                    ->first();

                if ($bundleItem) {
                    $posItem  = \App\Models\PosItem::find($bundleItem->pos_item_id);
                    $required = $item->quantity * $bundleItem->quantity;
                }
            }

            if ($posItem) {
                // C-02: Use atomic conditional decrement to prevent race condition.
                // If another request depleted stock between the check above and now,
                // affected rows = 0 and we throw to roll back the transaction.
                $affected = DB::table('pos_items')
                    ->where('id', $posItem->id)
                    ->where('stock', '>=', $required)
                    ->decrement('stock', $required);

                if (! $affected) {
                    throw new \RuntimeException("Stok {$posItem->name} tidak mencukupi saat dikonfirmasi.");
                }
            }
        }
    }
}
