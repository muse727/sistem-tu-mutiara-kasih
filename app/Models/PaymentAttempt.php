<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 3.7A — Payment attempt ledger.
 *
 * Represents one individual Midtrans payment session for a StudentBill.
 * A bill may accumulate many attempts before one settles.
 *
 * @property int         $id
 * @property int         $student_bill_id
 * @property string      $order_id
 * @property string|null $snap_token
 * @property string|null $transaction_id
 * @property string      $status
 * @property string|null $payment_method
 * @property string|null $bank
 * @property string|null $va_number
 * @property float       $gross_amount
 * @property \Carbon\Carbon      $initiated_at
 * @property \Carbon\Carbon|null $settled_at
 * @property \Carbon\Carbon|null $expired_at
 * @property string|null $source
 */
class PaymentAttempt extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'gross_amount'  => 'decimal:2',
        'initiated_at'  => 'datetime',
        'settled_at'    => 'datetime',
        'expired_at'    => 'datetime',
    ];

    // ── Status constants ──────────────────────────────────────────────────────
    // Mirror Midtrans transaction_status values exactly.

    const STATUS_PENDING    = 'pending';
    const STATUS_SETTLEMENT = 'settlement';
    const STATUS_CAPTURE    = 'capture';
    const STATUS_EXPIRE     = 'expire';
    const STATUS_CANCEL     = 'cancel';
    const STATUS_DENY       = 'deny';

    const TERMINAL_STATUSES = [
        self::STATUS_SETTLEMENT,
        self::STATUS_CAPTURE,
        self::STATUS_EXPIRE,
        self::STATUS_CANCEL,
        self::STATUS_DENY,
    ];

    const SUCCESS_STATUSES = [
        self::STATUS_SETTLEMENT,
        self::STATUS_CAPTURE,
    ];

    const FAILED_STATUSES = [
        self::STATUS_EXPIRE,
        self::STATUS_CANCEL,
        self::STATUS_DENY,
    ];

    // ── Source constants ──────────────────────────────────────────────────────

    const SOURCE_WEB    = 'WEB';
    const SOURCE_API    = 'API';
    const SOURCE_SYSTEM = 'SYSTEM';

    // ── Provider constants (Phase E.1) ───────────────────────────────────────

    const PROVIDER_MIDTRANS = 'MIDTRANS';
    const PROVIDER_FLIP     = 'FLIP';

    // Flip V2 canonical provider statuses (verified contract).
    const FLIP_STATUS_SUCCESSFUL = 'SUCCESSFUL';
    const FLIP_STATUS_PENDING    = 'PENDING';
    const FLIP_STATUS_FAILED     = 'FAILED';
    const FLIP_STATUS_CANCELLED  = 'CANCELLED';
    const FLIP_STATUS_EXPIRED    = 'EXPIRED';

    /** Only SUCCESSFUL may settle a bill as PAID. */
    const FLIP_SUCCESS_STATUSES = [self::FLIP_STATUS_SUCCESSFUL];

    const FLIP_FAILED_STATUSES = [
        self::FLIP_STATUS_FAILED,
        self::FLIP_STATUS_CANCELLED,
        self::FLIP_STATUS_EXPIRED,
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function bill()
    {
        return $this->belongsTo(StudentBill::class, 'student_bill_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function isSettled(): bool
    {
        return in_array($this->status, self::SUCCESS_STATUSES, true);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, self::FAILED_STATUSES, true);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    // ── Provider helpers (Phase E.1) ──────────────────────────────────────────

    public function isFlip(): bool
    {
        return $this->provider === self::PROVIDER_FLIP;
    }

    /**
     * Does this attempt currently hold an open/awaiting-payment provider session?
     * Midtrans: status 'pending'. Flip: provider_status 'PENDING' / status 'pending'.
     */
    public function hasOpenProviderSession(): bool
    {
        if ($this->isFlip()) {
            return $this->isPending()
                && ! in_array($this->provider_status, self::FLIP_FAILED_STATUSES, true)
                && ! in_array($this->provider_status, self::FLIP_SUCCESS_STATUSES, true);
        }
        return $this->isPending();
    }

    /**
     * Settle this attempt from a verified Flip SUCCESSFUL callback.
     * Maps the provider status into the local ledger without touching StudentBill
     * (the BillingPaymentService owns bill state).
     */
    public function markFlipSettled(string $providerStatus, ?string $paymentChannel = null): void
    {
        $this->update(array_filter([
            'status'          => self::STATUS_SETTLEMENT,
            'provider_status' => $providerStatus,
            'payment_channel' => $paymentChannel,
            'settled_at'      => now(),
        ], static fn ($v) => $v !== null));
    }

    /** Mark this attempt terminally failed from a Flip failed/cancelled/expired status. */
    public function markFlipFailed(string $providerStatus): void
    {
        $this->update([
            'status'          => self::STATUS_EXPIRE,
            'provider_status' => $providerStatus,
            'expired_at'      => now(),
        ]);
    }
}
