<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_action_filters_meetings_for_student(): void
    {
        $student = User::factory()->student()->create(['max_meetings' => 5]);

        $enrollment = Enrollment::factory()->create(['user_id' => $student->id]);

        // 未来の予約面談
        $upcoming = Meeting::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'status' => MeetingStatus::Reserved->value,
            'scheduled_at' => Carbon::now()->addDays(2),
        ]);

        // 過去の完了面談
        $past = Meeting::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'status' => MeetingStatus::Completed->value,
            'scheduled_at' => Carbon::now()->subDays(2),
        ]);

        $action = app(IndexAction::class);

        // tab / filter = upcoming
        $upcomingResult = $action($student, ['tab' => 'upcoming']);
        $this->assertCount(1, $upcomingResult['meetings']);
        $this->assertTrue($upcomingResult['meetings']->contains($upcoming));

        // tab / filter = past
        $pastResult = $action($student, ['tab' => 'past']);
        $this->assertCount(1, $pastResult['meetings']);
        $this->assertTrue($pastResult['meetings']->contains($past));

        // tab / filter = all
        $allResult = $action($student, ['tab' => 'all']);
        $this->assertCount(2, $allResult['meetings']);
    }
}
