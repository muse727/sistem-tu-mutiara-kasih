<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 09D.2 — TU transactional integration outbox.
 *
 * When an authoritative financial fact becomes valid (SPP/KANTIN bill PAID, POS
 * cash sale settled), the corresponding TU Finance Contract v1 event is
 * persisted atomically in the SAME database transaction as the source-state
 * mutation. Source commit + outbox commit succeed together or not at all.
 *
 * MariaDB/MySQL types (NOT PostgreSQL): JSON payload, enum-ish strings, bigints.
 * Identity is the canonical contract key
 *   {source_system}:{event_type}:{source_record_id}
 * enforced by a UNIQUE constraint — one canonical event per source fact, no
 * application-only checks. event_id is a stable UUID generated once at event
 * creation (never regenerated on retry).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_outbox_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('event_id');                       // stable per fact
            $table->string('event_type', 50);               // spp.payment | pos.cash_sale | pos.kantin_payment
            $table->string('event_version', 10)->default('1.0');
            $table->string('contract_version', 10)->default('1.0');
            $table->string('source_system', 30)->default('TU');
            $table->string('source_record_id', 100);        // student_bills.id | pos_orders.id
            $table->string('idempotency_key', 191);         // TU:{event_type}:{source_record_id}
            $table->timestamp('occurred_at');               // authoritative source timestamp
            $table->json('payload');                        // deterministic contract-v1 snapshot
            $table->string('delivery_status', 30)->default('PENDING'); // PENDING|PROCESSING|DELIVERED|FAILED_RETRYABLE|FAILED_PERMANENT
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            // One canonical outbox event per (source_system, event_type, source_record_id).
            $table->unique(['source_system', 'event_type', 'source_record_id'], 'outbox_source_unique');
            // Idempotency key is also unique (contract-level identity).
            $table->unique('idempotency_key', 'outbox_idem_unique');

            // Index-friendly dispatch scanning.
            $table->index(['delivery_status', 'created_at'], 'outbox_dispatch_index');
            $table->index('event_type', 'outbox_type_index');
            $table->index('event_id', 'outbox_event_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_outbox_events');
    }
};
