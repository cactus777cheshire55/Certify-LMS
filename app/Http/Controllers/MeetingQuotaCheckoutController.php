<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Requests\MeetingQuota\CheckoutStoreRequest;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Services\StripePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingQuotaCheckoutController extends Controller
{
    /**
     * 追加面談購入画面
     */
    public function select(): View
    {
        // View 側の変数名 $plans に合わせて取得
        $plans = MeetingPack::where('status', 'published')
            ->orderBy('sort_order', 'asc')
            ->get();

        return view('meeting-quota.checkout-select', compact('plans'));
    }

    /**
     * Stripe Checkout へのリダイレクト
     */
    public function store(CheckoutStoreRequest $request, StripePaymentService $stripeService): RedirectResponse
    {
        $user = $request->user();
        $pack = MeetingPack::findOrFail($request->input('meeting_pack_id'));

        // 決済レコードの作成 (Pending)
        $payment = Payment::create([
            'user_id' => $user->id,
            'meeting_pack_id' => $pack->id,
            'amount' => $pack->price,
            'quantity' => $pack->meeting_count,
            'status' => PaymentStatus::Pending,
        ]);

        // Stripe Checkout Session 生成
        $session = $stripeService->createCheckoutSession($user, $pack, $payment);

        $payment->update([
            'stripe_session_id' => $session->id,
        ]);

        return redirect()->away($session->url);
    }

    /**
     * 決済完了画面
     */
    public function success(Request $request): View
    {
        $payment = null;

        // Stripe から戻された session_id から該当の決済情報を取得
        if ($sessionId = $request->query('session_id')) {
            $payment = Payment::with('meetingPack')
                ->where('stripe_session_id', $sessionId)
                ->first();
        }

        return view('meeting-quota.success', compact('payment'));
    }
}
