<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAsCoachAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IndexAsCoachActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_as_coach_filters_by_tab(): void
    {
        $coach = User::factory()->coach()->create();

        // 今後の面談
        $upcoming = Meeting::factory()->create([
            'coach_id' => $coach->id,
            'status' => MeetingStatus::Reserved->value,
            'scheduled_at' => Carbon::now()->addDay(),
        ]);

        // 過去の面談
        $past = Meeting::factory()->create([
            'coach_id' => $coach->id,
            'status' => MeetingStatus::Completed->value,
            'scheduled_at' => Carbon::now()->subDay(),
        ]);

        $action = app(IndexAsCoachAction::class);

        // tab = upcoming
        $upcomingResult = $action($coach, ['tab' => 'upcoming']);
        $this->assertCount(1, $upcomingResult['meetings']);
        $this->assertTrue($upcomingResult['meetings']->contains($upcoming));

        // tab = past
        $pastResult = $action($coach, ['tab' => 'past']);
        $this->assertCount(1, $pastResult['meetings']);
        $this->assertTrue($pastResult['meetings']->contains($past));

        // tab = all
        $allResult = $action($coach, ['tab' => 'all']);
        $this->assertCount(2, $allResult['meetings']);
    }
}