<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom price di pos_bundles tidak punya default value.
 * Field ini sudah tidak dipakai (dihapus dari $fillable & $casts di model)
 * tapi kolom masih ada di DB. Tambah DEFAULT 0 agar test yang masih
 * kirim/tidak kirim price tidak crash dengan "Field doesn't have a default value".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_bundles', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pos_bundles', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->change();
        });
    }
};
