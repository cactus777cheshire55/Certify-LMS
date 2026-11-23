<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\UseCases\Meeting\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_action_loads_necessary_relations(): void
    {
        $meeting = Meeting::factory()->create();
        MeetingMemo::factory()->create(['meeting_id' => $meeting->id]);

        $action = app(ShowAction::class);
        $result = $action($meeting);

        // 各リレーションがロードされていることを検証
        $this->assertTrue($result->relationLoaded('enrollment'));
        $this->assertTrue($result->relationLoaded('coach'));
        $this->assertTrue($result->relationLoaded('student'));
        $this->assertTrue($result->relationLoaded('canceledBy'));
        $this->assertTrue($result->relationLoaded('meetingMemo'));
        $this->assertNotNull($result->meetingMemo);
    }
}
