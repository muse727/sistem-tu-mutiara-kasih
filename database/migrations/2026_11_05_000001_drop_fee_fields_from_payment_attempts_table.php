<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAYMENT-FEE-CLEANUP-DB-01 — drop the six orphan fee columns.
 *
 * The PAYMENT-FEE-SETTLEMENT work was reverted (see
 * docs/PAYMENT-FEE-CLEANUP-IMPLEMENTATION.md). The application code now has
 * ZERO references to these columns; they remain physically only because the
 * original fee migrations (2026_11_03 / 2026_11_04) were applied to the LOCAL
 * development database before removal.
 *
 * This migration drops ONLY the six orphan columns:
 *   fee_payer, fee_payer_state, fee_amount,
 *   customer_surcharge_amount, net_amount, fee_amount_state
 *
 * It touches no other column/table, adds nothing back, and preserves every
 * existing payment_attempts row and all unrelated column values. Verified safe
 * on the LOCAL dev DB: no indexes, triggers, FKs, or views depend on these
 * columns, and all existing rows hold only default zero-fee backfill (no data
 * loss of substance).
 *
 * The drops are idempotent (existence-checked) so this migration is also safe
 * on databases that never received the original fee migrations (e.g. a fresh
 * test database), where it becomes a no-op.
 *
 * down() restores ONLY these six columns with the EXACT definitions taken from
 * the original migrations — no definitions are invented.
 */
return new class extends Migration
{
    private const FEE_COLUMNS = [
        'fee_payer',
        'fee_payer_state',
        'fee_amount',
        'customer_surcharge_amount',
        'net_amount',
        'fee_amount_state',
    ];

    public function up(): void
    {
        $toDrop = array_values(array_filter(
            self::FEE_COLUMNS,
            fn (string $column) => Schema::hasColumn('payment_attempts', $column)
        ));

        if ($toDrop === []) {
            return; // fee columns absent (e.g. fresh DB) — nothing to do.
        }

        Schema::table('payment_attempts', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_attempts', 'fee_payer')) {
            return; // already restored — do not duplicate columns.
        }

        Schema::table('payment_attempts', function (Blueprint $table) {
            // Exact definitions from the original (now-removed) fee migrations.
            $table->string('fee_payer', 20)->default('NONE')->after('gross_amount');
            $table->string('fee_payer_state', 20)->default('UNKNOWN')->after('fee_payer');
            $table->decimal('fee_amount', 12, 2)->default('0.00')->after('fee_payer_state');
            $table->decimal('customer_surcharge_amount', 12, 2)->default('0.00')->after('fee_amount');
            $table->decimal('net_amount', 12, 2)->nullable()->after('customer_surcharge_amount');
            $table->string('fee_amount_state', 20)->default('UNKNOWN')->after('net_amount');
        });
    }
};
