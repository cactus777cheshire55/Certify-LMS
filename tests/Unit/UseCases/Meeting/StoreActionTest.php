<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_action_creates_meeting_and_consumes_quota(): void
    {
        $student = User::factory()->student()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create(['meeting_url' => 'https://meet.google.com/abc-defg-hij']);
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $certification->coaches()->attach($coach->id, [
            'assigned_at' => now(),
            'assigned_by_user_id' => $coach->id,
        ]);

        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0, 0);

        CoachAvailability::create([
            'coach_id' => $coach->id,
            'day_of_week' => $nextMonday->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $action = app(StoreAction::class);
        $meeting = $action($enrollment, $nextMonday, '学習計画の相談');

        $this->assertInstanceOf(Meeting::class, $meeting);
        $this->assertSame(MeetingStatus::Reserved, $meeting->status);
        $this->assertSame($coach->id, $meeting->coach_id);
        $this->assertSame($student->id, $meeting->student_id);
        $this->assertNotNull($meeting->meeting_quota_transaction_id);
    }
}