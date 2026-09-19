<?php

namespace App\Services\Integration;

use App\Models\SppSettlementBatch;

/**
 * TU-FINANCE-BATCH-05A — end-to-end reconciliation orchestration (READ-ONLY).
 *
 * Loads a settlement batch, builds its deterministic reconciliation snapshot,
 * wraps it in the reconciliation request envelope, sends it across the
 * existing HMAC boundary via HmacIntegrationClient, and normalizes the Finance
 * response into a deterministic result for the caller.
 *
 * DETECTION ONLY. Never mutates StudentBill / PaymentAttempt / batch
 * membership, never posts or repairs accounting, never creates outbox or
 * accounting events. Repeated calls for unchanged state return the same
 * result. Transport failures are kept strictly distinct from accounting
 * mismatches: a MISMATCHED/PENDING reconciliation result is a Finance ANSWER,
 * not a transport failure.
 */
class SppSettlementBatchReconciliationTransportService
{
    /** Normalized result kinds returned to the caller. */
    public const RESULT_RECONCILED   = 'RECONCILED';   // Finance returned a reconciliation status (body['reconciliation'])
    public const RESULT_INVALID_BATCH = 'INVALID_BATCH'; // batch not found / not a settlement batch in TU
    public const RESULT_UNAVAILABLE  = 'UNAVAILABLE';  // Finance unreachable / timeout / 5xx
    public const RESULT_AUTH_FAILED  = 'AUTH_FAILED';  // HMAC rejected
    public const RESULT_CONTRACT_REJECTED = 'CONTRACT_REJECTED'; // envelope/version/snapshot rejected by Finance

    public const ENDPOINT = '/api/integration/tu/reconciliation';

    public function __construct(
        protected SppSettlementBatchReconciliationSnapshot $snapshots,
        protected HmacIntegrationClient $client,
    ) {}

    /**
     * Reconcile a batch by its batch_id (UUID). Pure read + one signed POST.
     *
     * @return array{result:string, ...} deterministic normalized result.
     */
    public function reconcileByBatchId(string $batchId): array
    {
        $batch = SppSettlementBatch::where('batch_id', $batchId)->first();
        if (! $batch) {
            return $this->normalize(self::RESULT_INVALID_BATCH, $batchId, null, null, 'batch not found');
        }

        return $this->reconcile($batch);
    }

    /**
     * Reconcile a loaded batch. Read-only; the snapshot is evidence only.
     *
     * @return array{result:string, ...}
     */
    public function reconcile(SppSettlementBatch $batch): array
    {
        $snapshot = $this->snapshots->build($batch); // read-only

        $request = [
            'event_type'       => 'spp.reconciliation',
            'event_version'    => '1.0',
            'contract_version' => '1.0',
            'source_system'    => 'TU',
            'batch_id'         => (string) $batch->batch_id,
            'source_record_id' => (string) $batch->batch_id,
            'idempotency_key'  => (string) $batch->idempotency_key,
            'snapshot'         => $snapshot,
        ];

        $r = $this->client->post(self::ENDPOINT, $request);

        return match ($r['outcome']) {
            HmacIntegrationClient::OK =>
                $this->normalize(self::RESULT_RECONCILED, (string) $batch->batch_id, $snapshot, $r['body'], null),
            HmacIntegrationClient::AUTH_FAILED =>
                $this->normalize(self::RESULT_AUTH_FAILED, (string) $batch->batch_id, $snapshot, $r['body'], $r['code'] ?? 'INVALID_SIGNATURE'),
            HmacIntegrationClient::UNAVAILABLE, HmacIntegrationClient::NOT_CONFIGURED =>
                $this->normalize(self::RESULT_UNAVAILABLE, (string) $batch->batch_id, $snapshot, $r['body'], $r['code'] ?? 'unavailable'),
            default => // CONTRACT_REJECTED
                $this->normalize(self::RESULT_CONTRACT_REJECTED, (string) $batch->batch_id, $snapshot, $r['body'], $r['code'] ?? 'contract_rejected'),
        };
    }

    /**
     * Normalize into a deterministic result. Reconciliation status (when
     * present) is surfaced verbatim from Finance — never coerced to HTTP
     * failure semantics.
     */
    protected function normalize(string $result, string $batchId, ?array $snapshot, ?array $body, ?string $reason): array
    {
        $reconciliation = is_array($body) ? ($body['reconciliation'] ?? null) : null;

        return [
            'result'          => $result,
            'batch_id'        => $batchId,
            'idempotency_key' => $snapshot['idempotency_key'] ?? null,
            // Finance reconciliation outcome (MATCHED / TU_MISMATCH / …), when Finance answered.
            'status'          => is_array($reconciliation) ? ($reconciliation['status'] ?? null) : null,
            'category'        => is_array($reconciliation) ? ($reconciliation['reason'] ?? null) : null,
            // TU-side totals (from the snapshot — read-only evidence).
            'tu'              => $snapshot === null ? null : [
                'total_amount'  => $snapshot['total_amount'] ?? null,
                'payment_count' => $snapshot['payment_count'] ?? null,
                'bill_count'    => $snapshot['bill_count'] ?? null,
                'batch_status'  => $snapshot['status'] ?? null,
            ],
            // Finance-side outcome fields (when available).
            'finance'         => is_array($reconciliation) ? [
                'total_amount'           => $reconciliation['finance_amount'] ?? null,
                'finance_transaction_id' => $reconciliation['finance_transaction_id'] ?? null,
                'batch_status'           => $reconciliation['batch_status'] ?? null,
            ] : null,
            'reconciliation'  => $reconciliation, // full deterministic Finance result (no secrets)
            'reason'          => $reason,
            'http_status'     => is_array($body) ? null : null,
        ];
    }
}
