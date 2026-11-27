<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Webhook;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Stripe\Event;
use Tests\TestCase;

/**
 * 【レビュー指摘 3: Stripe Webhookの冪等性と失敗時処理の不備】の回帰テスト（ドラフト）。
 *
 * 既存の tests/Feature/Http/Webhook/StripeWebhookTest.php は
 * 「checkout.session.completed を逐次2回送信した場合に冪等になる」ことしか検証しておらず、
 * (a) 決済失敗・セッション期限切れイベントでの Payment ステータス遷移
 * (b) Payment 取得～ステータス確認が行ロック（lockForUpdate 等）で保護されているか
 * のいずれも未カバーである。
 *
 * @group external-api
 */
class StripeWebhookFailureAndIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function mockWebhookEvent(string $type, string $sessionId): Event
    {
        $event = new Event;
        $event->type = $type;
        $event->data = (object) [
            'object' => (object) [
                'id' => $sessionId,
            ],
        ];

        $mockService = Mockery::mock(StripePaymentService::class);
        $mockService->shouldReceive('constructWebhookEvent')
            ->with(Mockery::any(), 'valid_signature')
            ->andReturn($event);
        $this->app->instance(StripePaymentService::class, $mockService);

        return $event;
    }

    /**
     * (a) checkout.session.expired（セッション期限切れ）受信時に、
     * pending のまま放置されず failed へ遷移すること、かつ面談回数が付与されないこと。
     *
     * 現状の StripeWebhookController は checkout.session.completed 以外のイベントを
     * 一切ハンドリングしていないため、本テストは失敗する（status が pending のまま残る）。
     */
    public function test_checkout_session_expired_marks_payment_as_failed(): void
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
            'stripe_session_id' => 'cs_test_expired',
            'status' => PaymentStatus::Pending->value,
        ]);

        $this->mockWebhookEvent('checkout.session.expired', 'cs_test_expired');

        $response = $this->postJson(
            route('webhooks.stripe'),
            ['id' => 'evt_test_expired'],
            ['Stripe-Signature' => 'valid_signature']
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Failed->value,
        ]);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    /**
     * (b) Payment の冪等性チェック（status === Succeeded か）が、
     * 行ロック(lockForUpdate)付きで取得したうえで行われているかを検証する。
     *
     * 真の並行リクエストをテストプロセス内で再現するのは難しいため、発行される SQL をキャプチャし、
     * Payment 取得クエリに `for update` が含まれているか（= 同時webhook到達時に片方が
     * 排他的に待たされる構造になっているか）を直接の根拠とする。
     *
     * 現状の StripeWebhookController::__invoke は `Payment::where(...)->first()` で取得しており
     * lockForUpdate を使っていないため、本テストは失敗する。
     */
    public function test_payment_lookup_for_webhook_processing_is_row_locked(): void
    {
        $user = User::factory()->student()->create(['max_meetings' => 2]);
        $pack = MeetingPack::factory()->create([
            'status' => 'published',
            'meeting_count' => 3,
        ]);
        Payment::create([
            'user_id' => $user->id,
            'meeting_pack_id' => $pack->id,
            'amount' => $pack->price,
            'quantity' => $pack->meeting_count,
            'stripe_session_id' => 'cs_test_lock',
            'status' => PaymentStatus::Pending->value,
        ]);

        $this->mockWebhookEvent('checkout.session.completed', 'cs_test_lock');

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = Str::lower($query->sql);
        });

        $this->postJson(
            route('webhooks.stripe'),
            ['id' => 'evt_test_lock'],
            ['Stripe-Signature' => 'valid_signature']
        )->assertStatus(200);

        $hasRowLock = collect($queries)->contains(
            fn (string $sql) => Str::contains($sql, ['for update', 'lock in share mode'])
        );

        $this->assertTrue(
            $hasRowLock,
            'Payment の取得（冪等性チェック対象）は、並行webhookによる二重加算を防ぐため lockForUpdate 等で行ロックすべき'
        );
    }
}
