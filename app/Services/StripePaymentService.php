<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Event;
use Stripe\Stripe;
use Stripe\Webhook;

class StripePaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Stripe Checkout Session の生成
     */
    public function createCheckoutSession(User $user, MeetingPack $pack, Payment $payment): StripeSession
    {
        return StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'jpy',
                    'product_data' => [
                        'name' => $pack->name,
                    ],
                    'unit_amount' => $pack->price,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('meeting-quota.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('meeting-quota.checkout.select'),
            'client_reference_id' => (string) $payment->id,
            'customer_email' => $user->email,
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'user_id' => (string) $user->id,
            ],
        ]);
    }

    /**
     * Webhook 署名の検証
     */
    public function constructWebhookEvent(string $payload, string $sigHeader): Event
    {
        return Webhook::constructEvent(
            $payload,
            $sigHeader,
            config('services.stripe.webhook_secret')
        );
    }
}
