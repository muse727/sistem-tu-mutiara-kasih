<?php

namespace App\Services\Integration;

use App\Models\SppSettlementBatch;
use App\Models\SppSettlementBatchItem;
use App\Models\StudentBill;
use Carbon\Carbon;

/**
 * TU-FINANCE-BATCH-04 — reconciliation snapshot exporter (READ-ONLY).
 *
 * Produces a deterministic, normalized snapshot of one settlement batch that a
 * caller transports to Finance (e.g. over the existing HMAC boundary) for the
 * Finance-side SppSettlementBatchReconciliationService to compare against
 * Finance accounting state. TU never reads Finance's database and never posts
 * or repairs anything — it only emits facts.
 *
 * The snapshot carries: batch identity + idempotency key, lifecycle status,
 * total/counts/breakdown, the recomputed live aggregation (so Finance can
 * detect a frozen-snapshot divergence), and the member bill references
 * (bill_id, amount, operational payment key, month/year) for traceability.
 * No Finance COA IDs are included (TU does not know them).
 */
class SppSettlementBatchReconciliationSnapshot
{
    /**
     * Build the normalized snapshot array for a batch. Pure read — no writes.
     */
    public function build(SppSettlementBatch $batch): array
    {
        $items = SppSettlementBatchItem::where('spp_settlement_batch_id', $batch->id)
            ->orderBy('student_bill_id')
            ->get();

        $billIds = $items->pluck('student_bill_id')->all();
        $bills = $billIds ? StudentBill::whereIn('id', $billIds)->get()->keyBy('id') : collect();

        // Live recomputation of the aggregation from member bills (independent
        // of the frozen snapshot columns) so Finance can detect divergence.
        $liveTotal = '0.00';
        $paymentKeys = [];
        $liveBillCount = 0;
        $members = [];

        foreach ($items as $item) {
            $bill = $bills->get($item->student_bill_id);
            if (! $bill) {
                continue;
            }
            $amount = $this->decimal($bill->amount);
            $liveTotal = bcadd($liveTotal, $amount, 2);
            $paymentKeys[$item->operational_payment_key] = true;
            $liveBillCount++;

            $members[] = [
                'student_bill_id'         => (int) $item->student_bill_id,
                'amount'                  => $amount,
                'payment_method'          => (string) $item->payment_method,
                'provider'                => $item->provider,
                'operational_payment_key' => (string) $item->operational_payment_key,
                'bill_month'              => $bill->bill_month !== null ? (int) $bill->bill_month : null,
                'bill_year'               => $bill->bill_year !== null ? (int) $bill->bill_year : null,
            ];
        }

        return [
            'event_type'       => 'spp.settlement_batch',
            'source_system'    => 'TU',
            'batch_id'         => (string) $batch->batch_id,
            'source_record_id' => (string) $batch->batch_id,
            'idempotency_key'  => (string) $batch->idempotency_key,
            'category'         => (string) $batch->category,
            'period_kind'      => (string) $batch->period_kind,
            'period_start'     => $batch->period_start->toDateString(),
            'period_end'       => $batch->period_end->toDateString(),
            'occurred_at'      => $batch->occurred_at
                ? Carbon::parse($batch->occurred_at)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i:sP')
                : null,
            'status'           => (string) $batch->status,
            // Frozen accounting snapshot (what was / will be sent).
            'total_amount'     => $this->decimal($batch->total_amount),
            'payment_count'    => (int) $batch->payment_count,
            'bill_count'       => (int) $batch->bill_count,
            'breakdown'        => $batch->breakdown ?? [],
            // Live recomputation from member bills.
            'live_total_amount' => $liveTotal,
            'live_payment_count' => count($paymentKeys),
            'live_bill_count'   => $liveBillCount,
            // Member bill references for traceability.
            'members'          => $members,
        ];
    }

    /** Exact decimal(12,2) → 2dp string; no float arithmetic. */
    protected function decimal($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
