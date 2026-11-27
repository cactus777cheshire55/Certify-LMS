<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 【レビュー指摘 2: キャンセル済み枠の再予約不可（DB一意制約）】の回帰テスト（ドラフト）。
 *
 * 既存の tests/Feature/Http/Meeting/MeetingControllerTest.php::test_store_blocks_double_booking_for_same_coach_and_slot
 * は「reserved 同士の二重予約が阻止される」ことしか検証しておらず、「一度キャンセルした枠に対して
 * 同じコーチ・日時で再予約できる」ことまでは保証していない。
 *
 * 現状は database/migrations/2026_11_23_171153_update_meetings_unique_constraint.php により
 * reserved_scheduled_at（status='reserved' の時だけ scheduled_at を持つ生成カラム）に対して
 * UNIQUE を掛け直しているため、本テストは現状の実装でも成功するはずである。
 * 失敗した場合は、一意制約がステータスに関係なく掛かる状態に後退している（リグレッション）ことを意味する。
 */
class MeetingCancelRebookTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_rebooking_the_same_coach_and_slot_succeeds_after_cancellation(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        // 1回目の予約
        $firstResponse = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '1回目の相談',
        ]);
        $firstResponse->assertRedirect();
        $firstMeeting = Meeting::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('scheduled_at', $scheduledAt)
            ->first();
        $this->assertNotNull($firstMeeting);

        // キャンセル
        $cancelResponse = $this->actingAs($student)->post(route('meetings.cancel', $firstMeeting));
        $cancelResponse->assertRedirect();
        $this->assertSame(MeetingStatus::Canceled, $firstMeeting->fresh()->status);

        // 同じコーチ・同じ日時枠で再予約 → DB の UNIQUE 制約エラーにならず成功すること
        $secondResponse = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '2回目の相談（再予約）',
        ]);

        $secondResponse->assertRedirect();
        $secondResponse->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('meetings', [
            'coach_id' => $coach->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'status' => MeetingStatus::Reserved->value,
            'topic' => '2回目の相談（再予約）',
        ]);
        $this->assertDatabaseCount('meetings', 2);
    }
}
