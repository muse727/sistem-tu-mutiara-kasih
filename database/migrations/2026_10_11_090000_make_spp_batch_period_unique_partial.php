<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TU-FINANCE-BATCH-03-FIX (F-1) — allow overflow batches per period.
 *
 * The original constraint UNIQUE(source_system, category, period_kind,
 * period_start) permitted exactly ONE batch per period, which made the F-1
 * "route a late settlement to a new batch after close" impossible. It is
 * replaced with a PARTIAL unique index that enforces **at most one DRAFT
 * (open) batch per period** while allowing any number of CLOSED/overflow
 * batches that share the same period_start.
 *
 * - MariaDB/MySQL (production): a generated `period_window` column that is
 *   'OPEN' only for DRAFT rows (NULL otherwise) + UNIQUE(source_system,
 *   category, period_kind, period_start, period_window). NULLs are distinct,
 *   so only one DRAFT row per period can exist.
 * - SQLite (tests): a true partial unique index with WHERE status = 'DRAFT'.
 *
 * The UNIQUE(idempotency_key) constraint is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('spp_settlement_batches', function ($table) {
                $table->dropUnique('spp_batch_period_unique');
            });
            DB::statement(
                "CREATE UNIQUE INDEX spp_batch_period_unique ON spp_settlement_batches " .
                "(source_system, category, period_kind, period_start) WHERE status = 'DRAFT'"
            );
            return;
        }

        // MariaDB / MySQL
        DB::statement(
            "ALTER TABLE spp_settlement_batches " .
            "ADD COLUMN period_window VARCHAR(10) GENERATED ALWAYS AS " .
            "(CASE WHEN status = 'DRAFT' THEN 'OPEN' ELSE NULL END) STORED"
        );
        Schema::table('spp_settlement_batches', function ($table) {
            $table->dropUnique('spp_batch_period_unique');
        });
        Schema::table('spp_settlement_batches', function ($table) {
            $table->unique(
                ['source_system', 'category', 'period_kind', 'period_start', 'period_window'],
                'spp_batch_period_unique'
            );
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS spp_batch_period_unique');
            Schema::table('spp_settlement_batches', function ($table) {
                $table->unique(['source_system', 'category', 'period_kind', 'period_start'], 'spp_batch_period_unique');
            });
            return;
        }

        Schema::table('spp_settlement_batches', function ($table) {
            $table->dropUnique('spp_batch_period_unique');
        });
        Schema::table('spp_settlement_batches', function ($table) {
            $table->dropColumn('period_window');
        });
        Schema::table('spp_settlement_batches', function ($table) {
            $table->unique(['source_system', 'category', 'period_kind', 'period_start'], 'spp_batch_period_unique');
        });
    }
};
