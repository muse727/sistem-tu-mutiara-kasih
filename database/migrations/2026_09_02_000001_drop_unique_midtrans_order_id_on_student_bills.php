<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unique constraints on student_bills.midtrans_order_id
 * and payment_attempts.order_id.
 *
 * Multi-bill payment (MULTI- prefix order IDs) requires one order_id
 * to be shared across multiple bill rows and multiple attempt rows.
 * Both unique constraints blocked that. Replace with plain indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_bills', function (Blueprint $table) {
            $table->dropUnique('student_bills_midtrans_order_id_unique');
            $table->index('midtrans_order_id', 'idx_student_bills_midtrans_order_id');
        });

        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropUnique('payment_attempts_order_id_unique');
            $table->index('order_id', 'idx_payment_attempts_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_bills', function (Blueprint $table) {
            $table->dropIndex('idx_student_bills_midtrans_order_id');
            $table->unique('midtrans_order_id', 'student_bills_midtrans_order_id_unique');
        });

        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropIndex('idx_payment_attempts_order_id');
            $table->unique('order_id', 'payment_attempts_order_id_unique');
        });
    }
};
