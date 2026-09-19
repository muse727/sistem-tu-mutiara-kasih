<?php

namespace App\Services\Integration;

use App\Models\IntegrationOutboxEvent;
use App\Models\PaymentAttempt;
use App\Models\SppSettlementBatch;
use App\Models\SppSettlementBatchItem;
use App\Models\StudentBill;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TU-FINANCE-BATCH-02 — SPP settlement batch aggregation, close, and dispatch.
 *
 * Aggregates settled SPP receipts (canonical timestamp = StudentBill.paid_at,
 * Asia/Jakarta) into period batches. Operational payment grain: one
 * operational_payment_key per operational payment — a Midtrans MULTI order is
 * ONE payment even though it settles many bills. The per-(payment_method,
 * provider) breakdown is preserved so Finance can post multi-debit journals
 * (e.g. Dr Cash / Dr Bank BCA / Dr Bank BSI); TU never sends Finance COA IDs.
 *
 * Feature flag: when integration.spp_batch_enabled is FALSE, none of this runs
 * and the existing per-bill spp.payment integration is unchanged. When TRUE,
 * eligible settlements become batch members; the per-bill outbox row is still
 * recorded for trace but is NOT dispatched for Finance accounting (see
 * OutboxDispatcher::isAccountingEvent).
 */
class SppSettlementBatchService
{
    /**
     * Record an eligible settled SPP bill into the current open (DRAFT) batch.
     * Must be called inside the settlement transaction (same atomicity as the
     * per-bill outbox). No-op when batch mode is disabled, the bill is not a
     * verified SPP PAID, it predates the activation boundary, or it is already
     * a batch member. Never throws — integration must never affect payment.
     */
    public function recordEligibleSettlement(StudentBill $bill): void
    {
        try {
            if (! config('integration.spp_batch_enabled', false)) {
                return;
            }
            if ($bill->type !== 'SPP' || $bill->status !== 'PAID') {
                return;
            }
            if (! $this->batchEligible($bill)) {
                return; // forward-only activation boundary (no backfill)
            }
            if (SppSettlementBatchItem::where('student_bill_id', $bill->id)->exists()) {
                return; // already batched — cross-batch duplication impossible
            }

            $paidAt = Carbon::parse($bill->paid_at)->setTimezone('Asia/Jakarta');
            [$periodStart, $periodEnd, $kind] = $this->periodFor($paidAt);

            // F-1 (BATCH-03-FIX) — the membership write + recompute run under a
            // row lock on the CURRENT batch row so the close/dispatch race cannot
            // fold a late settlement into a CLOSED batch (whose outbox payload was
            // already built). If the period's canonical batch is already closed,
            // the settlement is routed to a NEW overflow batch for the same period
            // instead of mutating the frozen one. Membership + totals stay atomic
            // in this single transaction.
            DB::transaction(function () use ($bill, $paidAt, $periodStart, $periodEnd, $kind) {
                $batch = $this->openBatchFor($kind, $periodStart, $periodEnd, $paidAt);

                // Reload under lock so we evaluate isClosed() on current state.
                $locked = SppSettlementBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
                if ($locked->isClosed()) {
                    // Late settlement after close — never mutate the CLOSED batch.
                    $locked = $this->openOverflowBatch($kind, $periodStart, $periodEnd, $paidAt);
                    $locked = SppSettlementBatch::whereKey($locked->id)->lockForUpdate()->firstOrFail();
                }

                SppSettlementBatchItem::firstOrCreate(
                    ['student_bill_id' => $bill->id],
                    [
                        'spp_settlement_batch_id' => $locked->id,
                        'payment_method'          => (string) $bill->payment_method,
                        'provider'                => $this->provider($bill),
                        'operational_payment_key' => $this->operationalPaymentKey($bill),
                    ]
                );

                // recompute operates on the locked/current row (DRAFT only).
                $this->recompute($locked);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SPP batch record failed (non-fatal)', [
                'bill_id' => $bill->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Close an open batch: freeze the accounting snapshot and mark CLOSED.
     * Idempotent; a batch with no members is left DRAFT (nothing to post).
     */
    public function close(SppSettlementBatch $batch): SppSettlementBatch
    {
        return DB::transaction(function () use ($batch) {
            $locked = SppSettlementBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($locked->isClosed()) {
                return $locked; // already closed — no reopen
            }
            $this->recompute($locked);
            if ($locked->bill_count === 0) {
                return $locked; // nothing settled in this period — keep DRAFT
            }
            $locked->forceFill([
                'status'    => SppSettlementBatch::STATUS_CLOSED,
                'closed_at' => now(),
            ])->save();
            return $locked->fresh();
        });
    }

    /**
     * Build the deterministic spp.settlement_batch outbox event for a CLOSED
     * batch (idempotent) so OutboxDispatcher + HmacIntegrationTransport deliver
     * it. Returns null unless the batch is CLOSED.
     */
    public function buildOutboxEvent(SppSettlementBatch $batch): ?IntegrationOutboxEvent
    {
        if ($batch->status !== SppSettlementBatch::STATUS_CLOSED) {
            return null;
        }

        $payload = [
            'category'      => 'SPP',
            'period_kind'   => $batch->period_kind,
            'period_start'  => $batch->period_start->toDateString(),
            'period_end'    => $batch->period_end->toDateString(),
            'total_amount'  => $this->decimal($batch->total_amount),
            'payment_count' => (int) $batch->payment_count,
            'bill_count'    => (int) $batch->bill_count,
            'breakdown'     => $batch->breakdown ?? [],
            'description'   => sprintf(
                'SPP settlement %s (%d payments, %d bills)',
                $batch->period_start->toDateString(),
                (int) $batch->payment_count,
                (int) $batch->bill_count
            ),
        ];

        return IntegrationOutboxEvent::firstOrCreate(
            [
                'source_system'    => 'TU',
                'event_type'       => IntegrationOutboxEvent::EVENT_SPP_SETTLEMENT_BATCH,
                'source_record_id' => (string) $batch->batch_id,
            ],
            [
                'event_id'         => (string) $batch->batch_id,
                'event_version'    => '1.0',
                'contract_version' => '1.0',
                'idempotency_key'  => (string) $batch->idempotency_key,
                'occurred_at'      => $batch->occurred_at,
                'payload'          => $payload,
                'delivery_status'  => IntegrationOutboxEvent::STATUS_PENDING,
                'attempt_count'    => 0,
            ]
        );
    }

    /** Recompute totals/counts/breakdown from member bills (DRAFT only). */
    public function recompute(SppSettlementBatch $batch): void
    {
        $items = SppSettlementBatchItem::where('spp_settlement_batch_id', $batch->id)->get();
        $billIds = $items->pluck('student_bill_id')->all();
        $bills = $billIds ? StudentBill::whereIn('id', $billIds)->get()->keyBy('id') : collect();

        $total = '0.00';
        $paymentKeys = [];
        $buckets = [];

        foreach ($items as $item) {
            $bill = $bills->get($item->student_bill_id);
            if (! $bill) {
                continue;
            }
            $amount = $this->decimal($bill->amount);
            $total = bcadd($total, $amount, 2);
            $paymentKeys[$item->operational_payment_key] = true;

            $bkey = $item->payment_method . '|' . ($item->provider ?? '');
            if (! isset($buckets[$bkey])) {
                $buckets[$bkey] = [
                    'payment_method' => $item->payment_method,
                    'provider'       => $item->provider,
                    'amount'         => '0.00',
                    '_payments'      => [],
                    'bill_count'     => 0,
                ];
            }
            $buckets[$bkey]['amount'] = bcadd($buckets[$bkey]['amount'], $amount, 2);
            $buckets[$bkey]['_payments'][$item->operational_payment_key] = true;
            $buckets[$bkey]['bill_count']++;
        }

        $breakdown = [];
        foreach ($buckets as $b) {
            $breakdown[] = [
                'payment_method' => $b['payment_method'],
                'provider'       => $b['provider'],
                'amount'         => $b['amount'],
                'payment_count'  => count($b['_payments']),
                'bill_count'     => $b['bill_count'],
            ];
        }

        $batch->forceFill([
            'total_amount'  => $total,
            'payment_count' => count($paymentKeys),
            'bill_count'    => $items->count(),
            'breakdown'     => $breakdown,
        ])->save();
    }

    // ── Internals ────────────────────────────────────────────────────────────

    protected function openBatchFor(string $kind, Carbon $start, Carbon $end, Carbon $paidAt): SppSettlementBatch
    {
        return SppSettlementBatch::firstOrCreate(
            [
                'source_system' => 'TU',
                'category'      => 'SPP',
                'period_kind'   => $kind,
                'period_start'  => $start->toDateString(),
            ],
            [
                'batch_id'        => (string) Str::uuid(),
                'idempotency_key' => SppSettlementBatch::idempotencyKey($kind, $start->toDateString()),
                'period_end'      => $end->toDateString(),
                'status'          => SppSettlementBatch::STATUS_DRAFT,
                'occurred_at'     => $end->copy()->endOfDay(),
            ]
        );
    }

    /**
     * F-1 (BATCH-03-FIX) — open (or reuse) the OVERFLOW batch for a period whose
     * canonical batch is already CLOSED. Late settlements are routed here so a
     * frozen batch is never mutated. The overflow batch keeps the accurate
     * period_start/period_end but uses a unique idempotency key derived from its
     * own batch_id (it can never collide with the canonical batch's key). The
     * period-window partial unique index enforces at most ONE DRAFT batch per
     * period, so the overflow batch is itself the single open batch for the
     * period until it too is closed.
     */
    protected function openOverflowBatch(string $kind, Carbon $start, Carbon $end, Carbon $paidAt): SppSettlementBatch
    {
        // Reuse the period's current OPEN (DRAFT) overflow batch if one exists.
        $open = SppSettlementBatch::where('source_system', 'TU')
            ->where('category', 'SPP')
            ->where('period_kind', $kind)
            ->where('period_start', $start->toDateString())
            ->where('status', SppSettlementBatch::STATUS_DRAFT)
            ->lockForUpdate()
            ->first();
        if ($open) {
            return $open;
        }

        $batchId = (string) Str::uuid();
        return SppSettlementBatch::create([
            'batch_id'        => $batchId,
            'idempotency_key' => SppSettlementBatch::overflowIdempotencyKey($kind, $batchId),
            'source_system'   => 'TU',
            'category'        => 'SPP',
            'period_kind'     => $kind,
            'period_start'    => $start->toDateString(),
            'period_end'      => $end->toDateString(),
            'status'          => SppSettlementBatch::STATUS_DRAFT,
            'occurred_at'     => $end->copy()->endOfDay(),
        ]);
    }

    /** Resolve [period_start, period_end, kind] for a settlement date. */
    protected function periodFor(Carbon $date): array
    {
        $kind = strtoupper((string) config('integration.spp_batch_period_kind', 'DAILY'));
        return match ($kind) {
            'WEEKLY'  => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek(), 'WEEKLY'],
            'MONTHLY' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth(), 'MONTHLY'],
            default   => [$date->copy()->startOfDay(), $date->copy()->endOfDay(), 'DAILY'],
        };
    }

    /**
     * Forward-only activation boundary: only settlements at/after
     * integration.spp_batch_activated_at become batch members (no backfill).
     */
    protected function batchEligible(StudentBill $bill): bool
    {
        $activatedAt = config('integration.spp_batch_activated_at');
        if (! $activatedAt) {
            return true;
        }
        try {
            $boundary = Carbon::parse($activatedAt);
            return Carbon::parse($bill->paid_at)->greaterThanOrEqualTo($boundary);
        } catch (\Throwable) {
            return true;
        }
    }

    /** One key per operational payment (MULTI = one payment, many bills). */
    protected function operationalPaymentKey(StudentBill $bill): string
    {
        if (in_array($bill->payment_method, ['MIDTRANS', 'ONLINE'], true) && $bill->midtrans_order_id) {
            return 'PG:' . $bill->midtrans_order_id;
        }
        return 'CASH:' . $bill->id;
    }

    /** Mirror IntegrationOutboxService::provider() — lowercase midtrans/flip/null. */
    protected function provider(StudentBill $bill): ?string
    {
        if (! in_array($bill->payment_method, ['MIDTRANS', 'ONLINE'], true)) {
            return null;
        }
        $attempt = \App\Models\PaymentAttempt::where('student_bill_id', $bill->id)
            ->where('status', \App\Models\PaymentAttempt::STATUS_SETTLEMENT)
            ->latest('settled_at')->first();
        if ($attempt && $attempt->provider === \App\Models\PaymentAttempt::PROVIDER_FLIP) {
            return 'flip';
        }
        return $bill->payment_method === 'MIDTRANS' ? 'midtrans' : null;
    }

    protected function decimal($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
