<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $student1;

    private User $student2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student1 = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        $this->student2 = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);
    }

    /** @test */
    public function 未ログインユーザーは通知一覧にアクセスできずリダイレクトされる(): void
    {
        $this->get(route('notifications.index'))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function ログインユーザーは自分の通知一覧を閲覧できる(): void
    {
        $this->actingAs($this->student1)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertViewIs('notifications.index');
    }

    /** @test */
    public function 他人の通知を既読にしようとすると403エラーになる(): void
    {
        // student1 宛の通知を作成
        $this->student1->notify(new QaReplyReceivedNotification(
            QaReply::factory()->create()
        ));
        $notification = $this->student1->notifications()->first();

        // student2 が既読化を試みる
        $this->actingAs($this->student2)
            ->post(route('notifications.markAsRead', $notification))
            ->assertForbidden();
    }

    /** @test */
    public function 自分の通知を既読にすると指定_ur_lへリダイレクトされる(): void
    {
        $this->student1->notify(new QaReplyReceivedNotification(
            QaReply::factory()->create()
        ));
        $notification = $this->student1->notifications()->first();

        $this->actingAs($this->student1)
            ->post(route('notifications.markAsRead', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /** @test */
    public function 全件既読化を実行すると未読通知がすべて既読になる(): void
    {
        $this->student1->notify(new QaReplyReceivedNotification(QaReply::factory()->create()));
        $this->student1->notify(new QaReplyReceivedNotification(QaReply::factory()->create()));

        $this->assertEquals(2, $this->student1->unreadNotifications()->count());

        $this->actingAs($this->student1)
            ->post(route('notifications.markAllAsRead'))
            ->assertRedirect(route('notifications.index'));

        $this->assertEquals(0, $this->student1->unreadNotifications()->count());
    }

    /** @test */
    public function q_aの回答投稿時に質問者に通知とメールが送信される(): void
    {
        // 1. テストデータの準備
        $cert = Certification::factory()->create(['status' => CertificationStatus::Published]);
        $thread = QaThread::factory()->create([
            'user_id' => $this->student1->id,
            'certification_id' => $cert->id,
        ]);

        // 2. 通知のモック化
        Notification::fake();

        // 3. student2 が回答を投稿（アクション）
        $response = $this->actingAs($this->student2)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => 'テスト回答メッセージ',
            ]);

        // 4. スレッド詳細画面への正常なリダイレクトを検証
        $response->assertRedirect(route('qa-board.show', $thread));

        // 5. 質問者（student1）へ正しい通知が送信されたことを検証
        Notification::assertSentTo(
            $this->student1,
            QaReplyReceivedNotification::class
        );
    }

    public function 通知一覧で未読フィルタタブを選択すると未読の通知のみが取得される(): void
    {
        // 未読通知と既読通知を作成
        $this->student1->notify(new QaReplyReceivedNotification(QaReply::factory()->create()));
        $this->student1->notify(new QaReplyReceivedNotification(QaReply::factory()->create()));

        $unreadNotification = $this->student1->unreadNotifications()->first();
        $readNotification = $this->student1->unreadNotifications()->last();
        $readNotification->markAsRead(); // 1件を既読化

        // 未読フィルタ (tab=unread) でアクセス
        $response = $this->actingAs($this->student1)
            ->get(route('notifications.index', ['tab' => 'unread']));

        $response->assertOk();

        // ビューに渡された通知一覧に未読のみが含まれていることを検証
        $notifications = $response->viewData('notifications');
        $this->assertTrue($notifications->contains($unreadNotification));
        $this->assertFalse($notifications->contains($readNotification));
    }

    /** @test */
    public function 自分の通知一覧には他人の通知が含まれない(): void
    {
        // student1 と student2 にそれぞれ通知を作成
        $this->student1->notify(new QaReplyReceivedNotification(QaReply::factory()->create()));
        $this->student2->notify(new QaReplyReceivedNotification(QaReply::factory()->create()));

        $response = $this->actingAs($this->student1)
            ->get(route('notifications.index'));

        $response->assertOk();
        $notifications = $response->viewData('notifications');

        // student1 の通知一覧には 1 件のみ存在し、student2 宛の通知が含まれないこと
        $this->assertCount(1, $notifications);
        $this->assertEquals($this->student1->id, $notifications->first()->notifiable_id);
    }

    /** @test */
    public function 質問者本人が自分の質問に回答した場合は通知が送信されない(): void
    {
        Notification::fake();

        $cert = Certification::factory()->create(['status' => CertificationStatus::Published]);
        $thread = QaThread::factory()->create([
            'user_id' => $this->student1->id,
            'certification_id' => $cert->id,
        ]);

        // 質問者本人（student1）が自分のスレッドに回答を投稿
        $this->actingAs($this->student1)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '自分で補足コメントします。',
            ]);

        // 通知が一切送信されていないことを検証
        Notification::assertNothingSent();
    }

    /** @test */
    public function すでに既読の通知を再度クリックしてもエラーにならずリダイレクトされる(): void
    {
        $this->student1->notify(new QaReplyReceivedNotification(QaReply::factory()->create()));
        $notification = $this->student1->notifications()->first();
        $notification->markAsRead(); // 事前に既読化

        // 既読済みの状態で再度 POST
        $this->actingAs($this->student1)
            ->post(route('notifications.markAsRead', $notification))
            ->assertRedirect();
    }
}
