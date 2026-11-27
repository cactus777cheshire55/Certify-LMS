<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripePaymentService $stripeService): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        try {
            $event = $stripeService->constructWebhookEvent($payload, $sigHeader);
        } catch (Throwable $e) {
            Log::warning('Stripe Webhook Signature Verification Failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            return $this->handleCheckoutSessionCompleted($event->data->object);
        }

        if ($event->type === 'checkout.session.expired') {
            return $this->handleCheckoutSessionExpired($event->data->object);
        }

        return response()->json(['status' => 'success']);
    }

    private function handleCheckoutSessionCompleted(object $session): JsonResponse
    {
        return DB::transaction(function () use ($session): JsonResponse {
            $payment = $this->findLockedPayment($session);

            if (! $payment) {
                Log::warning('Stripe Webhook: Payment Record Not Found', ['session_id' => $session->id]);

                return response()->json(['status' => 'payment_not_found'], 200);
            }

            // 冪等性（二重処理防止）チェック
            if ($payment->status === PaymentStatus::Succeeded) {
                return response()->json(['status' => 'already_processed']);
            }

            // 1. Payment の状態更新
            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'stripe_payment_intent_id' => $session->payment_intent ?? null,
                'paid_at' => now(),
            ]);

            // 2. 面談回数の付加トランザクション起票
            MeetingQuotaTransaction::create([
                'user_id' => $payment->user_id,
                'type' => 'purchased',
                'amount' => $payment->quantity,
                'related_payment_id' => $payment->id,
                'note' => "追加面談パック「{$payment->meetingPack->name}」の購入",
                'occurred_at' => now(),
            ]);

            return response()->json(['status' => 'success']);
        });
    }

    private function handleCheckoutSessionExpired(object $session): JsonResponse
    {
        return DB::transaction(function () use ($session): JsonResponse {
            $payment = $this->findLockedPayment($session);

            if (! $payment) {
                Log::warning('Stripe Webhook: Payment Record Not Found', ['session_id' => $session->id]);

                return response()->json(['status' => 'payment_not_found'], 200);
            }

            // すでに完了・失敗済みの場合は何もしない（冪等性チェック）
            if ($payment->status !== PaymentStatus::Pending) {
                return response()->json(['status' => 'already_processed']);
            }

            $payment->update([
                'status' => PaymentStatus::Failed,
            ]);

            return response()->json(['status' => 'success']);
        });
    }

    private function findLockedPayment(object $session): ?Payment
    {
        $paymentId = $session->metadata->payment_id ?? null;

        return Payment::where(function ($query) use ($paymentId, $session) {
            if ($paymentId !== null) {
                $query->where('id', $paymentId)
                    ->orWhere('stripe_session_id', $session->id);
            } else {
                $query->where('stripe_session_id', $session->id);
            }
        })
            ->lockForUpdate()
            ->first();
    }
}
