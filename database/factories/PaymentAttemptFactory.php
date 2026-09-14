<?php

namespace Database\Factories;

use App\Models\PaymentAttempt;
use App\Models\StudentBill;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Phase 3.7A — PaymentAttempt factory for tests.
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        $bill    = StudentBill::factory()->create();
        $orderId = 'BILL-' . $bill->id . '-' . Str::random(6) . '-' . time();

        return [
            'student_bill_id'    => $bill->id,
            'provider'           => 'MIDTRANS',
            'provider_reference' => $orderId,
            'provider_status'    => PaymentAttempt::STATUS_PENDING,
            'order_id'           => $orderId,
            'snap_token'         => Str::random(36),
            'transaction_id'     => null,
            'status'             => PaymentAttempt::STATUS_PENDING,
            'payment_method'     => null,
            'payment_channel'    => null,
            'payment_url'        => null,
            'bank'               => null,
            'va_number'          => null,
            'gross_amount'       => 200000,
            'initiated_at'       => now(),
            'settled_at'         => null,
            'expired_at'         => null,
            'source'             => PaymentAttempt::SOURCE_WEB,
        ];
    }

    /** A Flip Accept Payment V2 pending attempt (19-digit reference string). */
    public function flip(StudentBill $bill, ?string $billLinkId = null): static
    {
        $billLinkId = $billLinkId ?? ('250209143025123' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT));

        return $this->state(fn () => [
            'student_bill_id'    => $bill->id,
            'provider'           => 'FLIP',
            'provider_reference' => $billLinkId, // 19-digit STRING
            'provider_status'    => 'ACTIVE',    // verified creation status (awaiting payment)
            'order_id'           => 'FLIP-' . $bill->id . '-' . Str::random(6) . '-' . time(),
            'snap_token'         => null,
            'transaction_id'     => null,
            'status'             => PaymentAttempt::STATUS_PENDING,
            'payment_method'     => 'ONLINE',
            'payment_channel'    => null,
            'payment_url'        => 'https://flip.id/sandbox/#bill' . $billLinkId,
            'gross_amount'       => $bill->amount,
            'initiated_at'       => now(),
        ]);
    }

    /** Attempt that expired. */
    public function expired(): static
    {
        return $this->state(fn () => [
            'status'     => PaymentAttempt::STATUS_EXPIRE,
            'snap_token' => null,
            'expired_at' => now(),
        ]);
    }

    /** Attempt that settled successfully. */
    public function settled(): static
    {
        return $this->state(fn () => [
            'status'         => PaymentAttempt::STATUS_SETTLEMENT,
            'snap_token'     => null,
            'transaction_id' => Str::random(20),
            'payment_method' => 'bank_transfer',
            'bank'           => 'bca',
            'settled_at'     => now(),
        ]);
    }

    /** Attempt for a specific bill. */
    public function forBill(StudentBill $bill): static
    {
        $orderId = 'BILL-' . $bill->id . '-' . Str::random(6) . '-' . time();

        return $this->state(fn () => [
            'student_bill_id' => $bill->id,
            'order_id'        => $orderId,
            'gross_amount'    => $bill->amount,
        ]);
    }
}
