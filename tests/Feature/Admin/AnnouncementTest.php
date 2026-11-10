<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AnnouncementTargetType;
use App\Enums\CertificationStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student1;

    private User $student2;

    private User $withdrawnStudent;

    private User $coach;

    private Certification $certification;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. 管理者アカウント
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);

        // 2. コーチアカウント
        $this->coach = User::factory()->create(['role' => UserRole::Coach]);

        // 3. 学習中受講生アカウント
        $this->student1 = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        $this->student2 = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        // 4. 退会済み受講生アカウント
        $this->withdrawnStudent = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::Withdrawn,
        ]);

        // 5. 公開中資格と受講登録（student1 のみ受講中）
        $this->certification = Certification::factory()->create([
            'status' => CertificationStatus::Published,
        ]);

        Enrollment::factory()->create([
            'user_id' => $this->student1->id,
            'certification_id' => $this->certification->id,
            'status' => EnrollmentStatus::Learning,
        ]);
    }

    /* =========================================================================
     * 1. 認可・アクセス制御の検証 (Authorization & Guest Protection)
     * ========================================================================= */

    /** @test */
    public function 未ログインユーザーは管理画面のお知らせ配信機能および通知詳細にアクセスできずログイン画面へリダイレクトされる(): void
    {
        $announcement = Announcement::factory()->create([
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->get(route('admin.announcements.index'))
            ->assertRedirect(route('login'));

        $this->get(route('admin.announcements.create'))
            ->assertRedirect(route('login'));

        $this->post(route('admin.announcements.store'), [])
            ->assertRedirect(route('login'));

        $this->get(route('admin.announcements.show', $announcement))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function 受講生およびコーチは管理画面のお知らせ配信機能にアクセスできず403エラーになる(): void
    {
        $announcement = Announcement::factory()->create([
            'created_by_user_id' => $this->admin->id,
        ]);

        // 受講生のアクセス遮断
        $this->actingAs($this->student1)
            ->get(route('admin.announcements.index'))
            ->assertForbidden();

        $this->actingAs($this->student1)
            ->post(route('admin.announcements.store'), [
                'title' => '不正投稿',
                'body' => '本文',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ])
            ->assertForbidden();

        // コーチのアクセス遮断
        $this->actingAs($this->coach)
            ->get(route('admin.announcements.create'))
            ->assertForbidden();

        $this->actingAs($this->coach)
            ->get(route('admin.announcements.show', $announcement))
            ->assertForbidden();
    }

    /** @test */
    public function 管理者は配信履歴一覧・新規配信・配信詳細画面を表示できる(): void
    {
        $announcement = Announcement::factory()->create([
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertViewIs('announcement.management.index');

        $this->actingAs($this->admin)
            ->get(route('admin.announcements.create'))
            ->assertOk()
            ->assertViewIs('announcement.management.create');

        $this->actingAs($this->admin)
            ->get(route('admin.announcements.show', $announcement))
            ->assertOk()
            ->assertViewIs('announcement.management.show');
    }

    /* =========================================================================
     * 2. お知らせ配信・正常系の検証 (Announcement Dispatching)
     * ========================================================================= */

    /** @test */
    public function 管理者は全受講生向けにお知らせを配信でき学習中の受講生のみに通知が届く(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => '全体メンテナンスのお知らせ',
                'body' => '明日10時よりシステムメンテナンスを実施します。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ]);

        $response->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success', 'お知らせを配信しました。');

        // DBに正しく件数が記録されているか（退会済みを除く学習中の2名のみ）
        $this->assertDatabaseHas('announcements', [
            'title' => '全体メンテナンスのお知らせ',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'dispatched_count' => 2,
            'created_by_user_id' => $this->admin->id,
        ]);

        // 学習中の受講生には通知送信、退会済みユーザーやコーチには非送信
        Notification::assertSentTo(
            [$this->student1, $this->student2],
            AdminAnnouncementNotification::class
        );
        Notification::assertNotSentTo(
            [$this->withdrawnStudent, $this->coach, $this->admin],
            AdminAnnouncementNotification::class
        );
    }

    /** @test */
    public function 管理者は特定資格の受講生に絞ってお知らせを配信できる(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => '資格限定のお知らせ',
                'body' => '教材が更新されました。',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $this->certification->id,
            ]);

        $response->assertRedirect(route('admin.announcements.index'));

        $this->assertDatabaseHas('announcements', [
            'title' => '資格限定のお知らせ',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $this->certification->id,
            'dispatched_count' => 1,
        ]);

        // student1 のみが対象資格を受講中
        Notification::assertSentTo($this->student1, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($this->student2, AdminAnnouncementNotification::class);
    }

    /** @test */
    public function 管理者は特定ユーザー1名にお知らせを配信できる(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => '個別連絡',
                'body' => '面談日の調整について',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $this->student2->id,
            ]);

        $response->assertRedirect(route('admin.announcements.index'));

        $this->assertDatabaseHas('announcements', [
            'title' => '個別連絡',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $this->student2->id,
            'dispatched_count' => 1,
        ]);

        Notification::assertSentTo($this->student2, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($this->student1, AdminAnnouncementNotification::class);
    }

    /* =========================================================================
     * 3. バリデーションの検証 (Validation Rules)
     * ========================================================================= */

    /** @test */
    public function 必須項目が未入力の場合はバリデーションエラーになる(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => '',
                'body' => '',
                'target_type' => '',
            ])
            ->assertSessionHasErrors(['title', 'body', 'target_type']);
    }

    /** @test */
    public function 配信対象タイプに応じた条件付き必須項目が未選択の場合はバリデーションエラーになる(): void
    {
        // 1. 資格指定タイプで対象資格が未選択の場合
        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'テストタイトル',
                'body' => 'テスト本文',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => null,
            ])
            ->assertSessionHasErrors(['target_certification_id']);

        // 2. ユーザー指定タイプで対象受講生が未選択の場合
        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'テストタイトル',
                'body' => 'テスト本文',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => null,
            ])
            ->assertSessionHasErrors(['target_user_id']);
    }

    /** @test */
    public function タイトルが200文字または本文が5000文字を超える場合はバリデーションエラーになる(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => Str::repeat('あ', 201),
                'body' => Str::repeat('い', 5001),
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ])
            ->assertSessionHasErrors(['title', 'body']);
    }

    /* =========================================================================
     * 4. 受講生側の通知閲覧・自動既読化の検証 (Student Notification Detail)
     * ========================================================================= */

    /** @test */
    public function 受講生は自分宛の通知詳細画面を表示でき未読状態から自動で既読化される(): void
    {
        $announcement = Announcement::factory()->create([
            'title' => '運営から重要なお知らせ',
            'body' => 'お知らせの全文です。',
            'created_by_user_id' => $this->admin->id,
        ]);

        // 受講生宛てにDB通知を直で生成（未読状態）
        $notification = $this->student1->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AdminAnnouncementNotification::class,
            'data' => [
                'title' => $announcement->title,
                'body' => $announcement->body,
                'notification_type' => 'admin_announcement',
                'announcement_id' => $announcement->id,
            ],
            'created_at' => now(),
        ]);

        // 初期状態は未読（read_at が null）
        $this->assertNull($notification->read_at);

        // 受講生本人が通知詳細を開く
        $this->actingAs($this->student1)
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertViewIs('notifications.show')
            ->assertSee('運営から重要なお知らせ')
            ->assertSee('お知らせの全文です。');

        // 詳細画面表示により read_at が更新され既読化されていることを検証
        $this->assertNotNull($notification->fresh()->read_at);
    }

    /** @test */
    public function 受講生が他人の通知詳細画面を表示しようとすると403エラーになる(): void
    {
        $announcement = Announcement::factory()->create([
            'created_by_user_id' => $this->admin->id,
        ]);

        // student1 宛ての通知を作成
        $notification = $this->student1->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AdminAnnouncementNotification::class,
            'data' => [
                'title' => $announcement->title,
                'body' => $announcement->body,
                'notification_type' => 'admin_announcement',
                'announcement_id' => $announcement->id,
            ],
            'created_at' => now(),
        ]);

        // student2 が student1 宛ての通知を開こうとすると遮断される
        $this->actingAs($this->student2)
            ->get(route('notifications.show', $notification))
            ->assertForbidden();
    }
}
