<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opsi C — Unifikasi hutang kantin ke student_bills.
 *
 * Tambah kolom pos_order_id ke student_bills sebagai link ke pos_orders.
 * Diisi saat checkout hutang di kasir (type=KANTIN).
 * NULL untuk tagihan SPP, PAKET, LAINNYA.
 *
 * ON DELETE SET NULL: kalau pos_orders dihapus, student_bills tetap ada
 * sebagai riwayat keuangan siswa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_bills', function (Blueprint $table) {
            $table->foreignId('pos_order_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('pos_orders')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('student_bills', function (Blueprint $table) {
            $table->dropForeign(['pos_order_id']);
            $table->dropColumn('pos_order_id');
        });
    }
};
