<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 09D.2 — dispatch pending/retryable integration outbox events.
// Uses the Null/local transport (09D.3 will inject HMAC-HTTPS). No network
// call leaves TU in this phase.
Artisan::command('integration:dispatch {--limit=50}', function () {
    $dispatcher = new \App\Services\Integration\OutboxDispatcher(
        app(\App\Services\Integration\IntegrationTransport::class)
    );
    $result = $dispatcher->dispatch((int) $this->option('limit'));
    $this->info(sprintf(
        'claimed=%d delivered=%d retryable=%d permanent=%d',
        $result['claimed'], $result['delivered'], $result['retryable'], $result['permanent']
    ));
})->purpose('Dispatch TU integration outbox events (Phase 09D.2)');

// TU-FINANCE-BATCH-02 — close open SPP settlement batches and queue their
// spp.settlement_batch outbox events for dispatch (idempotent). Safe to run
// repeatedly; a closed batch is never reopened and re-queuing is firstOrCreate.
Artisan::command('integration:spp-close-batches', function () {
    $svc = new \App\Services\Integration\SppSettlementBatchService();
    $batches = \App\Models\SppSettlementBatch::whereIn('status', [
        \App\Models\SppSettlementBatch::STATUS_DRAFT,
        \App\Models\SppSettlementBatch::STATUS_READY_TO_CLOSE,
    ])->get();
    $closed = 0; $queued = 0;
    foreach ($batches as $batch) {
        $b = $svc->close($batch);
        if ($b->status === \App\Models\SppSettlementBatch::STATUS_CLOSED) {
            $closed++;
            if ($svc->buildOutboxEvent($b)) {
                $queued++;
            }
        }
    }
    $this->info("closed={$closed} queued={$queued}");
})->purpose('Close open SPP settlement batches and queue batch events (BATCH-02)');

// TU-FINANCE-BATCH-04 — export a settlement batch reconciliation snapshot
// (READ-ONLY). Emits the normalized JSON a caller transports to Finance for
// the Finance-side SppSettlementBatchReconciliationService. No writes, no
// repair, no Finance access.
// TU-FINANCE-BATCH-04/05A — reconciliation entry point (READ-ONLY).
// Default: print the deterministic TU snapshot (evidence only).
// --send: run the full end-to-end orchestration — build the snapshot, send it
// across the existing HMAC boundary, and surface Finance's reconciliation
// result. Never posts or repairs accounting.
Artisan::command('integration:spp-reconcile-snapshot {batch_id : batch UUID} {--send : transport the snapshot to Finance and print the reconciliation result}', function (string $batch_id) {
    $batch = \App\Models\SppSettlementBatch::where('batch_id', $batch_id)->first();
    if (! $batch) {
        $this->error("batch not found: {$batch_id}");
        return 1;
    }
    if (! $this->option('send')) {
        $snapshot = (new \App\Services\Integration\SppSettlementBatchReconciliationSnapshot())->build($batch);
        $this->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return 0;
    }
    $result = app(\App\Services\Integration\SppSettlementBatchReconciliationTransportService::class)
        ->reconcile($batch);
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $result['result'] === \App\Services\Integration\SppSettlementBatchReconciliationTransportService::RESULT_RECONCILED ? 0 : 1;
})->purpose('SPP batch reconciliation: print snapshot (default) or --send end-to-end via HMAC (BATCH-04/05A, read-only)');
