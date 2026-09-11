<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hasUnique = collect(DB::select("SHOW INDEXES FROM student_rombels"))
            ->where('Key_name', 'siswa_rombel_unik')
            ->isNotEmpty();

        if ($hasUnique) {
            Schema::table('student_rombels', function (Blueprint $table) {
                // Ensure a generic index exists on student_id for foreign keys
                $table->index('student_id', 'student_rombels_student_id_index');
            });
            DB::statement('ALTER TABLE student_rombels DROP INDEX siswa_rombel_unik');
        }
    }

    public function down(): void
    {
        // Reverting this is risky due to potential duplicates, so we leave it empty or just re-add if needed
    }
};
