<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\CancelAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CancelActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_action_cancels_meeting_and_refunds_quota(): void
    {
        $student = User::factory()->student()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();

        // 未来の予約面談を作成
        $meeting = Meeting::factory()->create([
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'status' => MeetingStatus::Reserved->value,
            'scheduled_at' => Carbon::now()->addDays(2),
        ]);

        $action = app(CancelAction::class);
        $result = $action($meeting, $student);

        // 検証: ステータスが Canceled になり、canceled_by が記録されていること
        $this->assertSame(MeetingStatus::Canceled, $result->status);
        $this->assertSame($student->id, $result->canceled_by_user_id);
        $this->assertNotNull($result->canceled_at);
    }

    public function test_cancel_action_throws_exception_when_meeting_already_started(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        // 過去の予約面談
        $meeting = Meeting::factory()->create([
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'status' => MeetingStatus::Reserved->value,
            'scheduled_at' => Carbon::now()->subHour(),
        ]);

        $action = app(CancelAction::class);

        $this->expectException(MeetingAlreadyStartedException::class);
        $action($meeting, $student);
    }
}