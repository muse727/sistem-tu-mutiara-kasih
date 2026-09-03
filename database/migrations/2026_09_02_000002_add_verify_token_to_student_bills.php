<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add verify_token to student_bills for QR code verification.
 * Token is a random 32-char hex string, unique, nullable.
 * Generated at payment time (or backfilled for existing PAID bills).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_bills', function (Blueprint $table) {
            $table->string('verify_token', 64)->nullable()->unique()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_bills', function (Blueprint $table) {
            $table->dropUnique('student_bills_verify_token_unique');
            $table->dropColumn('verify_token');
        });
    }
};
