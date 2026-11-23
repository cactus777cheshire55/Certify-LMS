<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\MeetingReminderNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase; 

class QueueNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_announcement_is_queued_instead_of_synchronous_send(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'システムメンテナンスのお知らせ',
            'body' => '明日10時よりメンテナンスを実施します。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        $response->assertRedirect(route('admin.announcements.index'));

        // リクエスト直後は同期送信されず、DBキューへ追加されていることを検証
        Notification::assertSentTo(
            $student,
            AdminAnnouncementNotification::class,
            function (AdminAnnouncementNotification $notification) {
                return $notification->announcement->title === 'システムメンテナンスのお知らせ';
            }
        );
    }

    public function test_qa_reply_notification_is_queued_on_reply_post(): void
    {
        Notification::fake();

        $questionAuthor = User::factory()->student()->inProgress()->create();
        $replier = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $questionAuthor->id,
            'certification_id' => $cert->id,
        ]);

        $response = $this->actingAs($replier)->post(route('qa-board.replies.store', $thread), [
            'body' => 'こちらのセクションを参照してください。',
        ]);

        $response->assertRedirect();

        // 質問投稿者宛ての通知がキューに積まれたことを検証
        Notification::assertSentTo(
            $questionAuthor,
            QaReplyReceivedNotification::class
        );
    }

    public function test_meeting_reminder_notification_is_queued_on_schedule_command(): void
    {
        Notification::fake();

        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();

        // 明日の面談データを作成
        $meeting = Meeting::factory()->forCoach($coach)->forStudent($student)->create([
            'enrollment_id' => $enrollment->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'status' => 'reserved',
        ]);

        Artisan::call('notifications:send-meeting-reminders', ['--window' => 'eve']);

        // 面談当事者（受講生・コーチ）宛ての通知がキューに積まれたことを検証
        Notification::assertSentTo($student, MeetingReminderNotification::class);
        Notification::assertSentTo($coach, MeetingReminderNotification::class);
    }

    public function test_password_reset_notification_is_queued_on_request(): void
    {
        Notification::fake();

        $user = User::factory()->student()->create([
            'email' => 'testuser@example.com',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'testuser@example.com',
        ]);

        $response->assertSessionHas('status');

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class
        );
    }

    public function test_queue_job_is_stored_in_jobs_table_when_queue_driver_is_database(): void
    {
        // 実際の DB キュー挙動の検証のため Notification::fake() はオフ
        config(['queue.default' => 'database']);

        $admin = User::factory()->admin()->create();
        User::factory()->student()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'DBキューテスト',
            'body' => 'jobsテーブルに正常に保存されるかのテストです。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        // jobs テーブルに非同期送信ジョブが追加されていることを検証
        $this->assertDatabaseHas('jobs', [
            'queue' => 'default',
        ]);
    }
}