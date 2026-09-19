<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TU-FINANCE-BATCH-02 — settlement batch membership link.
 *
 * Links each settled StudentBill to exactly ONE settlement batch so every
 * included operational payment/bill traces back to its batch, and so a bill can
 * never appear in two batches (UNIQUE(student_bill_id)). Stores only the link +
 * the operational settlement bucket + the operational payment identity needed
 * for payment_count — it does NOT duplicate StudentBill financial data.
 *
 * operational_payment_key groups the bills of one operational payment:
 *  - online MULTI: the shared midtrans_order_id (one payment, many bills)
 *  - online single: the bill's own order_id
 *  - cash: 'CASH:{bill_id}' (one receipt = one payment)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spp_settlement_batch_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('spp_settlement_batch_id');
            $table->unsignedBigInteger('student_bill_id');
            $table->string('payment_method', 30);           // CASH | MIDTRANS | ONLINE
            $table->string('provider', 30)->nullable();     // midtrans | flip | null (cash)
            $table->string('operational_payment_key', 191); // one key per operational payment
            $table->timestamps();

            $table->foreign('spp_settlement_batch_id', 'spp_item_batch_fk')
                ->references('id')->on('spp_settlement_batches')->cascadeOnDelete();
            $table->foreign('student_bill_id', 'spp_item_bill_fk')
                ->references('id')->on('student_bills')->restrictOnDelete();

            // A bill belongs to exactly one batch — cross-batch duplication is impossible.
            $table->unique('student_bill_id', 'spp_item_bill_unique');
            $table->index(['spp_settlement_batch_id', 'operational_payment_key'], 'spp_item_paykey_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spp_settlement_batch_items');
    }
};
