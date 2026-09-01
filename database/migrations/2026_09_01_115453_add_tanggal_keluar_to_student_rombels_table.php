<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds tanggal_keluar (nullable timestamp) to student_rombels.
     * NULL  = siswa masih aktif di rombel ini
     * NOT NULL = siswa sudah keluar/pindah/lulus dari rombel ini pada tanggal tersebut
     */
    public function up(): void
    {
        Schema::table('student_rombels', function (Blueprint $table) {
            $table->timestamp('tanggal_keluar')->nullable()->after('tahun_ajaran_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_rombels', function (Blueprint $table) {
            $table->dropColumn('tanggal_keluar');
        });
    }
};
