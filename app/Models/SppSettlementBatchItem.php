<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TU-FINANCE-BATCH-02 — settlement batch membership link.
 *
 * Links a settled StudentBill to exactly ONE settlement batch
 * (UNIQUE(student_bill_id)). Stores only the link + operational settlement
 * bucket + operational payment identity — never duplicates StudentBill
 * financial data.
 */
class SppSettlementBatchItem extends Model
{
    protected $table = 'spp_settlement_batch_items';

    protected $fillable = [
        'spp_settlement_batch_id', 'student_bill_id',
        'payment_method', 'provider', 'operational_payment_key',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SppSettlementBatch::class, 'spp_settlement_batch_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(StudentBill::class, 'student_bill_id');
    }
}
