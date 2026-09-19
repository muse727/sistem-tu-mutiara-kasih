<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TU-FINANCE-BATCH-02 — SPP settlement batch aggregate.
 *
 * Groups many settled SPP receipts over a period into ONE Finance-facing
 * object so Finance posts one balanced journal per batch (cash basis:
 * Dr Cash/Bank, Cr SPP Revenue) instead of one transaction per StudentBill.
 *
 * Identity: batch_id (stable UUID) + deterministic idempotency_key
 *   TU:SPP:{PERIOD_KIND}:{period_start}
 * One canonical batch per (source_system, category, period_kind, period_start).
 *
 * Lifecycle: DRAFT → READY_TO_CLOSE → CLOSED → SENT → ACKNOWLEDGED, with
 * FAILED_RETRYABLE / FAILED_PERMANENT. A CLOSED batch's accounting snapshot
 * (total_amount, counts, breakdown) is immutable — never reopened; corrections
 * are new batches.
 *
 * MariaDB/MySQL types (consistent with integration_outbox_events).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spp_settlement_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('batch_id');                       // stable, generated once
            $table->string('idempotency_key', 191);         // TU:SPP:{PERIOD_KIND}:{period_start}
            $table->string('source_system', 30)->default('TU');
            $table->string('category', 20)->default('SPP');
            $table->string('period_kind', 10);              // DAILY | WEEKLY | MONTHLY
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->unsignedInteger('payment_count')->default(0);
            $table->unsignedInteger('bill_count')->default(0);
            // Per-settlement-bucket breakdown: [{payment_method, provider, amount, payment_count, bill_count}]
            $table->json('breakdown')->nullable();
            $table->string('status', 20)->default('DRAFT'); // DRAFT|READY_TO_CLOSE|CLOSED|SENT|ACKNOWLEDGED|FAILED_RETRYABLE|FAILED_PERMANENT
            $table->timestamp('occurred_at')->nullable();   // business date the batch represents
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            // One canonical batch per (source_system, category, period_kind, period_start).
            $table->unique(['source_system', 'category', 'period_kind', 'period_start'], 'spp_batch_period_unique');
            // Idempotency key is also unique (contract-level identity).
            $table->unique('idempotency_key', 'spp_batch_idem_unique');
            $table->index('status', 'spp_batch_status_index');
            $table->index('batch_id', 'spp_batch_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spp_settlement_batches');
    }
};
