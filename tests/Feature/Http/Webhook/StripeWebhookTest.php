<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Webhook;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用の Webhook シークレットをセット
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);
    }

    /**
     * 正しい Stripe-Signature ヘッダーを付与して Webhook を送信するヘルパー
     */
    private function postSignedWebhook(array $payload): TestResponse
    {
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$jsonPayload}", $this->webhookSecret);

        return $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $jsonPayload
        );
    }

    /**
     * checkout.session.completed イベントの正常系テスト（決済成功と残数加算・occurred_at の検証）
     */
    public function test_handles_checkout_session_completed_successfully(): void
    {
        $user = User::factory()->create();
        $pack = MeetingPack::factory()->create([
            'name' => '追加面談5回パック',
            'meeting_count' => 5,
            'price' => 5000,
            'status' => 'published',
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'meeting_pack_id' => $pack->id,
            'amount' => $pack->price,
            'quantity' => $pack->meeting_count,
            'status' => PaymentStatus::Pending,
            'stripe_session_id' => 'cs_test_123',
        ]);

        $payload = [
            'id' => 'evt_test_123',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'object' => 'checkout.session',
                    'payment_intent' => 'pi_test_123',
                ],
            ],
        ];

        // 署名付き Webhook リクエスト送信
        $response = $this->postSignedWebhook($payload);

        $response->assertOk();

        // 1. Payment ステータスが succeeded に更新されていること
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Succeeded->value,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        // 2. meeting_quota_transactions に購入レコードが追加されていること
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $user->id,
            'type' => 'purchased',
            'amount' => 5,
            'related_payment_id' => $payment->id,
        ]);

        // 3. occurred_at が正常に記録されていること
        $transaction = MeetingQuotaTransaction::where('related_payment_id', $payment->id)->first();
        $this->assertNotNull($transaction);
        $this->assertNotNull($transaction->occurred_at);
    }

    /**
     * 既に処理済みの Webhook (冪等性 / 二重計上防止) の動作テスト
     */
    public function test_ignores_already_processed_webhook(): void
    {
        $user = User::factory()->create();
        $pack = MeetingPack::factory()->create(['meeting_count' => 5]);
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'meeting_pack_id' => $pack->id,
            'amount' => $pack->price,
            'quantity' => $pack->meeting_count,
            'status' => PaymentStatus::Succeeded,
            'stripe_session_id' => 'cs_test_123',
        ]);

        $payload = [
            'id' => 'evt_test_456',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'object' => 'checkout.session',
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload);

        $response->assertOk();

        // 既に処理済みのため、重複したトランザクションが作られないこと
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    /**
     * 存在しない Stripe Session ID が送信された場合のテスト
     */
    public function test_handles_non_existent_payment_session(): void
    {
        $payload = [
            'id' => 'evt_test_789',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_non_existent',
                    'object' => 'checkout.session',
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload);

        $response->assertOk();
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }
}
