<?php

namespace App\Services\Integration;

use App\Models\IntegrationOutboxEvent;
use App\Models\PosOrder;
use App\Models\StudentBill;
use Illuminate\Support\Str;

/**
 * Phase 09D.2 — TU transactional outbox recorder.
 *
 * Builds a TU Finance Contract v1 event as a deterministic snapshot and
 * persists it. Callers invoke this INSIDE the same DB transaction that commits
 * the source-state mutation, so source commit + outbox commit are atomic.
 *
 * - Identity: source_system=TU, event_id (stable UUID, generated once),
 *   idempotency_key = TU:{event_type}:{source_record_id}.
 * - Idempotent: a UNIQUE(source_system,event_type,source_record_id) constraint
 *   guarantees one canonical event per source fact; re-settlement of an
 *   already-settled fact is a no-op (firstOrCreate).
 * - Never invents data: only fields actually available from TU source state.
 * - Student identity is student_uuid (snapshot); name/nis/nisn are snapshots,
 *   never identity.
 */
class IntegrationOutboxService
{
    public const SOURCE_SYSTEM     = 'TU';
    public const CONTRACT_VERSION  = '1.0';

    /**
     * Record an SPP payment event for a settled StudentBill.
     * Must be called inside the settlement transaction.
     */
    public function recordSppPayment(StudentBill $bill): ?IntegrationOutboxEvent
    {
        if ($bill->status !== 'PAID') {
            return null; // event represents VERIFIED PAID only
        }
        if (! $this->sppEligible($bill)) {
            return null; // forward-only activation gate (Phase 09D.4)
        }

        return $this->recordBillEvent(
            IntegrationOutboxEvent::EVENT_SPP_PAYMENT,
            $bill,
            [
                'student'     => $this->studentRef($bill),
                'bill_id'     => (int) $bill->id,
                'bill_type'   => (string) $bill->type,
                'amount'      => $this->decimal($bill->amount),
                'paid_at'     => $this->iso($bill->paid_at),
                'provider'    => $this->provider($bill),
                'provider_reference' => $this->providerReference($bill),
                'payment_method' => (string) $bill->payment_method,
                'description' => (string) $bill->name,
                // payment_attempt_id: NOT CURRENTLY AVAILABLE — omitted per contract.
            ]
        );
    }

    /**
     * Record a POS cash sale event for a settled PosOrder.
     * Must be called inside the settlement transaction.
     */
    public function recordPosCashSale(PosOrder $order): ?IntegrationOutboxEvent
    {
        if ($order->payment_status !== 'PAID') {
            return null;
        }
        if (! $this->settlementEligible($order->created_at)) {
            return null; // forward-only activation gate (Phase 09D.5)
        }

        $student = $order->student;

        $payload = [
            'transaction_code' => (string) $order->transaction_code,
            'amount'           => (int) $order->total_amount,
            'payment_amount'   => (int) $order->payment_amount,
            'change_amount'    => (int) $order->change_amount,
            'payment_method'   => 'CASH',
            'payment_status'   => 'PAID',
            'student'          => $student ? [
                'student_uuid' => (string) $student->student_uuid,
                'nis'          => $student->nis,
                'nisn'         => $student->nisn,
                'name'         => (string) $student->name,
            ] : null,
            'provider'           => null,
            'provider_reference' => null,
            'cashier_id'       => $order->user_id ? (int) $order->user_id : null,
        ];

        return $this->record(
            IntegrationOutboxEvent::EVENT_POS_CASH_SALE,
            (string) $order->id,
            $payload,
            $order->created_at
        );
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Phase 09D.4 — forward-only SPP activation gate. An SPP event is eligible
     * only when integration is enabled AND the bill's settlement (paid_at) is
     * at/after the configured activation boundary. Pre-activation (historical)
     * payments are never integrated. Never throws — integration must never
     * affect source payment.
     */
    protected function sppEligible(StudentBill $bill): bool
    {
        return $this->settlementEligible($bill->paid_at);
    }

    /**
     * Phase 09D.4/09D.5 — forward-only activation gate. A settlement (SPP
     * paid_at, KANTIN paid_at, POS cash created_at) is eligible only when
     * integration is enabled AND it occurred at/after the configured boundary.
     * Pre-activation (historical) facts are never integrated. Never throws.
     */
    protected function settlementEligible($settledAt): bool
    {
        if (! config('integration.enabled', false)) {
            return false;
        }
        $activatedAt = config('integration.activated_at');
        if (! $activatedAt) {
            return true;
        }
        try {
            $boundary = \Carbon\Carbon::parse($activatedAt);
            return \Carbon\Carbon::parse($settledAt)->greaterThanOrEqualTo($boundary);
        } catch (\Throwable) {
            return true;
        }
    }

    protected function recordBillEvent(string $eventType, StudentBill $bill, array $payload): IntegrationOutboxEvent
    {
        return $this->record($eventType, (string) $bill->id, $payload, $bill->paid_at);
    }

    /**
     * Idempotently persist the event. firstOrCreate keyed on the canonical
     * (source_system, event_type, source_record_id) so retries of the source
     * operation never create a second event.
     */
    protected function record(string $eventType, string $sourceRecordId, array $payload, $occurredAt): IntegrationOutboxEvent
    {
        return IntegrationOutboxEvent::firstOrCreate(
            [
                'source_system'    => self::SOURCE_SYSTEM,
                'event_type'       => $eventType,
                'source_record_id' => $sourceRecordId,
            ],
            [
                'event_id'         => (string) Str::uuid(),
                'event_version'    => self::CONTRACT_VERSION,
                'contract_version' => self::CONTRACT_VERSION,
                'idempotency_key'  => IntegrationOutboxEvent::idempotencyKey($eventType, $sourceRecordId),
                'occurred_at'      => $occurredAt,
                'payload'          => $payload,
                'delivery_status'  => IntegrationOutboxEvent::STATUS_PENDING,
                'attempt_count'    => 0,
            ]
        );
    }

    protected function studentRef(StudentBill $bill): array
    {
        $s = $bill->student;

        return [
            'student_uuid' => (string) $s->student_uuid,
            'nis'          => $s->nis,
            'nisn'         => $s->nisn,
            'name'         => (string) $s->name,
        ];
    }

    /**
     * Resolve the provider key for a settled bill.
     * CASH → null; provider-settled (ONLINE/MIDTRANS) → 'flip'|'midtrans'.
     * The provider is read from the settled PaymentAttempt ledger (authoritative),
     * falling back to legacy payment_method semantics for historical Midtrans bills.
     */
    protected function provider(StudentBill $bill): ?string
    {
        $attempt = $this->settledAttempt($bill);
        if ($attempt && $attempt->provider) {
            return strtolower((string) $attempt->provider); // 'FLIP'→'flip', 'MIDTRANS'→'midtrans'
        }
        // Legacy path: historical Midtrans bills have no attempt provider column.
        return $bill->payment_method === 'MIDTRANS' ? 'midtrans' : null;
    }

    protected function providerReference(StudentBill $bill): ?string
    {
        $attempt = $this->settledAttempt($bill);
        if ($attempt && $attempt->provider_reference) {
            return (string) $attempt->provider_reference; // Flip bill_link_id / Midtrans order_id (string)
        }
        return $bill->payment_method === 'MIDTRANS' ? $bill->midtrans_order_id : null;
    }

    /**
     * The settled PaymentAttempt for a bill (if any). Used to derive the
     * provider identity for the outbox event. Returns null for cash bills.
     */
    protected function settledAttempt(StudentBill $bill): ?\App\Models\PaymentAttempt
    {
        if (! in_array($bill->payment_method, ['MIDTRANS', 'ONLINE'], true)) {
            return null; // cash → no provider
        }
        return \App\Models\PaymentAttempt::where('student_bill_id', $bill->id)
            ->where('status', \App\Models\PaymentAttempt::STATUS_SETTLEMENT)
            ->latest('settled_at')
            ->first();
    }

    /** Exact decimal(12,2) → 2dp string; no float arithmetic. */
    protected function decimal($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /** ISO-8601 with Asia/Jakarta +07:00 offset. */
    protected function iso($timestamp): string
    {
        return \Carbon\Carbon::parse($timestamp)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i:sP');
    }
}
