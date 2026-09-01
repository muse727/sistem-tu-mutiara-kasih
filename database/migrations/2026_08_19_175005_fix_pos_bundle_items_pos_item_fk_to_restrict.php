<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bug #3 Fix: Ganti onDelete('cascade') → onDelete('restrict') pada FK
 * pos_bundle_items.pos_item_id → pos_items.id
 *
 * Sebelumnya CASCADE menyebabkan: kalau admin hapus barang dari master POS,
 * semua pos_bundle_items yang mereferensikan barang itu ikut terhapus diam-diam.
 * Bundle tetap aktif tapi komposisinya rusak tanpa peringatan apapun.
 *
 * Setelah fix: penghapusan barang yang masih dipakai dalam bundle akan ditolak
 * oleh DB dengan FK violation — admin harus hapus dulu bundle-nya atau edit
 * komposisi bundle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_bundle_items', function (Blueprint $table) {
            // Drop FK lama (cascade)
            $table->dropForeign(['pos_item_id']);

            // Buat ulang FK dengan restrict
            $table->foreign('pos_item_id')
                  ->references('id')
                  ->on('pos_items')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('pos_bundle_items', function (Blueprint $table) {
            // Kembalikan ke cascade (state sebelum fix)
            $table->dropForeign(['pos_item_id']);

            $table->foreign('pos_item_id')
                  ->references('id')
                  ->on('pos_items')
                  ->onDelete('cascade');
        });
    }
};
