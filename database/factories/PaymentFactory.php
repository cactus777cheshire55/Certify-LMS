<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'meeting_pack_id' => MeetingPack::factory(),
            'stripe_session_id' => 'cs_test_'.Str::random(24),
            'stripe_payment_intent_id' => 'pi_'.Str::random(24),
            'amount' => 5000,
            'quantity' => 5,
            'status' => PaymentStatus::Pending,
            'paid_at' => null,
        ];
    }
}
