<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingRemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $coach;
    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);
        $this->coach = User::factory()->create([
            'role' => UserRole::Coach,
            'status' => UserStatus::InProgress,
        ]);
        $this->enrollment = Enrollment::factory()->create([
            'user_id' => $this->student->id,
        ]);
    }

    /** @test */
    public function 前日リマインダーコマンドで翌日の面談当事者に通知が送信され重複送信されない(): void
    {
        Notification::fake();

        // 翌日の面談を作成
        $meeting = Meeting::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->student->id,
            'coach_id' => $this->coach->id,
            'scheduled_at' => now()->addDay()->setHour(14)->setMinute(0),
            'status' => MeetingStatus::Reserved,
        ]);

        // コマンド実行（1回目）
        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(Command::SUCCESS);

        Notification::assertSentTo([$this->student, $this->coach], MeetingReminderNotification::class);
        $this->assertNotNull($meeting->fresh()->reminded_eve_at);

        // 再度コマンド実行（2回目・重複送信されないこと）
        Notification::fake();
        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(Command::SUCCESS);

        Notification::assertNothingSent();
    }

    /** @test */
    public function 一時間前リマインダーコマンドで直前の面談当事者に通知が送信され重複送信されない(): void
    {
        Notification::fake();

        // 1時間後の面談を作成 (60分後)
        $meeting = Meeting::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->student->id,
            'coach_id' => $this->coach->id,
            'scheduled_at' => now()->addMinutes(60),
            'status' => MeetingStatus::Reserved,
        ]);

        // コマンド実行（1回目）
        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(Command::SUCCESS);

        Notification::assertSentTo([$this->student, $this->coach], MeetingReminderNotification::class);
        $this->assertNotNull($meeting->fresh()->reminded_one_hour_at);

        // 再度コマンド実行（2回目・1時間前も重複送信されないこと）
        Notification::fake();
        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(Command::SUCCESS);

        Notification::assertNothingSent();
    }

    /** @test */
    public function キャンセル済みや対象時間ウィンドウ外の面談にはリマインダーが送信されない(): void
    {
        Notification::fake();

        // 1. キャンセル済みの翌日面談
        Meeting::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->student->id,
            'coach_id' => $this->coach->id,
            'scheduled_at' => now()->addDay(),
            'status' => MeetingStatus::Canceled,
        ]);

        // 2. 2日後の予約面談（前日対象外）
        Meeting::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->student->id,
            'coach_id' => $this->coach->id,
            'scheduled_at' => now()->addDays(2),
            'status' => MeetingStatus::Reserved,
        ]);

        // 3. 30分後の予約面談（1時間前対象外）
        Meeting::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $this->student->id,
            'coach_id' => $this->coach->id,
            'scheduled_at' => now()->addMinutes(30),
            'status' => MeetingStatus::Reserved,
        ]);

        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(Command::SUCCESS);

        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(Command::SUCCESS);

        Notification::assertNothingSent();
    }

    /** @test */
    public function 退会済みユーザーにはリマインダー通知が送信されない(): void
    {
        Notification::fake();

        $withdrawnStudent = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::Withdrawn,
        ]);

        Meeting::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id' => $withdrawnStudent->id,
            'coach_id' => $this->coach->id,
            'scheduled_at' => now()->addDay()->setHour(14)->setMinute(0),
            'status' => MeetingStatus::Reserved,
        ]);

        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(Command::SUCCESS);

        // コーチのみに送信され、退会済み受講生には非送信
        Notification::assertSentTo($this->coach, MeetingReminderNotification::class);
        Notification::assertNotSentTo($withdrawnStudent, MeetingReminderNotification::class);
    }

    /** @test */
    public function 無効なwindowオプションが指定された場合はエラーコードを返し終了する(): void
    {
        $this->artisan('notifications:send-meeting-reminders --window=invalid')
            ->assertExitCode(Command::FAILURE);
    }
}