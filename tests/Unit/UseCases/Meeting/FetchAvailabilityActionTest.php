<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Meeting\FetchAvailabilityAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FetchAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_availability_action_returns_formatted_slots(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $certification->coaches()->attach($coach->id, [
            'assigned_at' => now(),
            'assigned_by_user_id' => $coach->id,
        ]);

        $targetDate = Carbon::now()->next(Carbon::MONDAY);

        CoachAvailability::create([
            'coach_id' => $coach->id,
            'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'is_active' => true,
        ]);

        $action = app(FetchAvailabilityAction::class);
        $result = $action($enrollment, $targetDate);

        // レスポンス構造の検証
        $this->assertSame($targetDate->toDateString(), $result['date']);
        $this->assertIsArray($result['slots']);
        $this->assertNotEmpty($result['slots']);

        $firstSlot = $result['slots'][0];
        $this->assertArrayHasKey('slot_start', $firstSlot);
        $this->assertArrayHasKey('slot_end', $firstSlot);
        $this->assertArrayHasKey('available_coach_count', $firstSlot);
        $this->assertGreaterThanOrEqual(1, $firstSlot['available_coach_count']);
    }
}