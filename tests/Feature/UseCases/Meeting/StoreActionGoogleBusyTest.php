<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\GoogleCredential;
use App\Models\User;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 【レビュー指摘 1: Google予定(Busy)のあるコーチが割り当てられる可能性】の回帰テスト（ドラフト）。
 *
 * StoreAction::findAvailableCoaches は LMS 内の meetings テーブルのみでコーチの空き状況を判定しており、
 * MeetingAvailabilityService::slotsForCertification が行っている Google Calendar FreeBusy のチェックが
 * 欠落している。本テストでは、
 *   - coachBusy: Google Calendar 側で対象スロットが Busy
 *   - coachFree: Google 連携なし（常に Free）で、過去 30 日の完了件数が coachBusy より多い
 * という状況を作り、CoachMeetingLoadService::leastLoadedCoach が「完了件数が少ない」coachBusy を
 * 優先的に選ぶように仕向ける。これにより、たとえ空き枠表示（slotsForCertification）側は正しく
 * coachBusy を除外していても、予約確定（StoreAction）側は Google Busy を無視して coachBusy に
 * 割り当ててしまうことを検証する。
 *
 * 現状の実装では本テストは失敗する（coachBusy が割り当てられる）。
 * 修正後、coachFree が割り当てられる（または候補なしで例外になる）ことをもって合格基準とする。
 */
class StoreActionGoogleBusyTest extends TestCase
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

    public function test_store_does_not_assign_a_coach_who_is_busy_on_google_calendar(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        // Google 側で Busy なコーチ（過去 30 日の完了件数 = 0 → leastLoadedCoach で優先されやすい）
        $coachBusy = User::factory()->coach()->create();
        GoogleCredential::factory()->create([
            'user_id' => $coachBusy->id,
            'calendar_id' => 'coach-busy@example.com',
            'expires_at' => now()->addHour(),
        ]);
        $this->attachCoach($certification, $coachBusy, $admin);
        CoachAvailability::factory()->forCoach($coachBusy)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();

        // Google 連携なし＝常に Free なコーチ（過去 30 日の完了件数 = 1 とし、意図的に leastLoadedCoach で劣後させる）
        $coachFree = User::factory()->coach()->create();
        $this->attachCoach($certification, $coachFree, $admin);
        CoachAvailability::factory()->forCoach($coachFree)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $otherStudent = User::factory()->student()->create();
        \App\Models\Meeting::factory()->completed()->forCoach($coachFree)->forStudent($otherStudent)->create();

        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $freeBusyUrl = base64_decode('aHR0cHM6Ly93d3cuZ29vZ2xlYXBpcy5jb20vY2FsZW5kYXIvdjMvZnJlZUJ1c3k=');
        Http::fake([
            $freeBusyUrl => Http::response([
                'calendars' => [
                    'coach-busy@example.com' => [
                        'busy' => [
                            [
                                'start' => $scheduledAt->copy()->toRfc3339String(),
                                'end' => $scheduledAt->copy()->addHour()->toRfc3339String(),
                            ],
                        ],
                    ],
                ],
            ], 200),
            // createMeetingEvent（coachFree には GoogleCredential がないため呼ばれない）
            '*' => Http::response([], 200),
        ]);

        // 前提確認: 空き枠表示では coachBusy が正しく除外され、coachFree のみ 1 名が空きとして数えられている
        $slots = app(\App\Services\MeetingAvailabilityService::class)
            ->slotsForCertification($certification, $scheduledAt->copy()->startOfDay());
        $matchedSlot = $slots->first(fn (array $slot) => $slot['slot_start']->equalTo($scheduledAt));
        $this->assertNotNull($matchedSlot, '前提条件: 対象スロットが空き枠一覧に存在するはず');
        $this->assertSame(1, $matchedSlot['available_coach_count'], '前提条件: 空き枠表示では Google Busy の coachBusy を除外し coachFree のみが空きとして数えられるはず');

        // 本題: 予約確定時に Google Busy の coachBusy が割り当てられていないか
        $meeting = app(StoreAction::class)($enrollment, $scheduledAt, '相談したい');

        $this->assertNotSame(
            $coachBusy->id,
            $meeting->coach_id,
            'Google Calendar 側で Busy なコーチ(coachBusy)が割り当てられてはならない'
        );
        $this->assertSame($coachFree->id, $meeting->coach_id);
    }
}
