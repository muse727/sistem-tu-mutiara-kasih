<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase E.1 — Generalize payment_attempts for a payment-provider boundary.
 *
 * Adds provider-specific columns so a PaymentAttempt can represent either a
 * historical Midtrans session or a new Flip Accept Payment V2 bill, without
 * rewriting any historical rows.
 *
 * New columns (all nullable / safe defaults → historical Midtrans rows remain
 * representable and untouched):
 *   provider           'MIDTRANS' (legacy default) | 'FLIP'
 *   provider_reference provider's reference — Flip bill_link_id (19-digit STRING),
 *                      or Midtrans order_id. String-safe; never cast to int.
 *   provider_status    raw provider status string (e.g. SUCCESSFUL/PENDING/settlement)
 *   payment_url        Flip link_url (customer-facing hosted payment page)
 *   payment_channel    channel if actually returned (e.g. bank_transfer/qris)
 *
 * Historical Midtrans rows: provider backfilled to 'MIDTRANS', provider_reference
 * backfilled from order_id, provider_status backfilled from status. No data loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->string('provider', 20)->default('MIDTRANS')->after('student_bill_id');
            $table->string('provider_reference', 100)->nullable()->after('provider');
            $table->string('provider_status', 30)->nullable()->after('status');
            $table->string('payment_url', 512)->nullable()->after('snap_token');
            $table->string('payment_channel', 50)->nullable()->after('payment_method');

            $table->index(['provider', 'provider_reference'], 'pa_provider_ref_index');
        });

        // Backfill historical Midtrans rows (forward-compatible, no fabrication).
        DB::table('payment_attempts')
            ->whereNull('provider_reference')
            ->update([
                'provider'           => 'MIDTRANS',
                'provider_reference' => DB::raw('order_id'),
                'provider_status'    => DB::raw('status'),
            ]);
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropIndex('pa_provider_ref_index');
            $table->dropColumn([
                'provider',
                'provider_reference',
                'provider_status',
                'payment_url',
                'payment_channel',
            ]);
        });
    }
};
