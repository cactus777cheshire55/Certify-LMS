<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Webhook;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Tests\TestCase;

/**
 * @group external-api
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正規の署名付き Webhook を受信し、面談回数が正常に付与されること、
     * および 2 重送信された場合に冪等に処理（二重付与防止）されることを検証
     */
    public function test_webhook_grants_meeting_quota_and_prevents_duplicate_processing(): void
    {
        $user = User::factory()->student()->create(['max_meetings' => 2]);
        $pack = MeetingPack::factory()->create([
            'status' => 'published',
            'meeting_count' => 3,
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'meeting_pack_id' => $pack->id,
            'amount' => $pack->price,
            'quantity' => $pack->meeting_count,
            'stripe_session_id' => 'cs_test_123',
            'status' => PaymentStatus::Pending->value,
        ]);

        // Stripe Event モックの作成
        $mockEvent = new Event();
        $mockEvent->type = 'checkout.session.completed';
        $mockEvent->data = (object) [
            'object' => (object) [
                'id' => 'cs_test_123',
                'payment_intent' => 'pi_test_999',
            ],
        ];

        $mockService = Mockery::mock(StripePaymentService::class);
        $mockService->shouldReceive('constructWebhookEvent')
            ->with(Mockery::any(), 'valid_signature')
            ->andReturn($mockEvent);

        $this->app->instance(StripePaymentService::class, $mockService);

        // 1回目の Webhook 送信（正規署名）
        $response1 = $this->postJson(
            route('webhooks.stripe'),
            ['id' => 'evt_test_123'],
            ['Stripe-Signature' => 'valid_signature']
        );

        $response1->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Succeeded->value,
        ]);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $user->id,
            'amount' => 3,
        ]);

        // 2回目の同一 Webhook 送信（二重加算防止の確認）
        $response2 = $this->postJson(
            route('webhooks.stripe'),
            ['id' => 'evt_test_123'],
            ['Stripe-Signature' => 'valid_signature']
        );

        $response2->assertStatus(200)->assertJson(['status' => 'already_processed']);

        // トランザクションが1件のみであることを検証
        $this->assertDatabaseCount('meeting_quota_transactions', 1);
    }

    /**
     * 不正な署名（Invalid Signature）の場合に 400 Bad Request を返すことを検証
     */
    public function test_webhook_rejects_invalid_signature(): void
    {
        $mockService = Mockery::mock(StripePaymentService::class);
        $mockService->shouldReceive('constructWebhookEvent')
            ->with(Mockery::any(), 'invalid_signature')
            ->andThrow(SignatureVerificationException::factory('Invalid signature', null, 'invalid_signature'));

        $this->app->instance(StripePaymentService::class, $mockService);

        $response = $this->postJson(
            route('webhooks.stripe'),
            ['id' => 'evt_test_invalid'],
            ['Stripe-Signature' => 'invalid_signature']
        );

        $response->assertStatus(400);
    }

    /**
     * 署名ヘッダー欠落（Missing Signature）の場合に 400 Bad Request を返すことを検証
     */
    public function test_webhook_rejects_missing_signature(): void
    {
        $mockService = Mockery::mock(StripePaymentService::class);
        $mockService->shouldReceive('constructWebhookEvent')
            ->with(Mockery::any(), '')
            ->andThrow(SignatureVerificationException::factory('Missing signature', null, ''));

        $this->app->instance(StripePaymentService::class, $mockService);

        $response = $this->postJson(
            route('webhooks.stripe'),
            ['id' => 'evt_test_missing']
            // Stripe-Signature ヘッダーなし
        );

        $response->assertStatus(400);
    }
}  