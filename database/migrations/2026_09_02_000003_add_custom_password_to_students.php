<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add custom_password to students table.
 * Nullable — if set, used for login instead of birth_date fallback.
 * Stored as bcrypt hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('custom_password')->nullable()->after('birth_date');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('custom_password');
        });
    }
};
