<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Http\Requests\Meeting\AvailabilityRequest;
use App\Http\Requests\Meeting\IndexAsCoachRequest;
use App\Http\Requests\Meeting\IndexRequest;
use App\Http\Requests\Meeting\StoreRequest;
use App\Http\Requests\Meeting\UpsertMemoRequest;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\Models\User;
use App\Services\CoachMeetingLoadService;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Database\Eloquent\Collection;

/**
 * 1on1 面談予約 (Meeting) の HTTP エントリポイント。
 *
 * 受講生視点(index / show / create / store / cancel / fetchAvailability)とコーチ視点
 * (indexAsCoach / upsertMemo)を 1 Controller に集約する。
 */
class MeetingController extends Controller
{
    /**
     * 受講生本人の面談一覧。filter (upcoming/past/all) クエリで履歴を切り替える。
     */
    public function index(IndexRequest $request, MeetingQuotaService $meetingQuota): View
    {
        $filter = $request->validated('filter') ?? 'upcoming';

        $query = Meeting::query()
            ->with('enrollment.certification', 'coach')
            ->forStudent($request->user())
            ->orderByDesc('scheduled_at');

        $meetings = match ($filter) {
            'past' => $query->past()->paginate(20),
            'all' => $query->paginate(20),
            default => $query->upcoming()->paginate(20),
        };

        return view('meeting.index', [
            'meetings' => $meetings,
            'filter' => $filter,
            'meetingsRemaining' => $meetingQuota->remaining($request->user()),
        ]);
    }

    /**
     * コーチ宛の面談一覧。担当受講生 / 受講登録での絞り込みを併せて提供する。
     */
    public function indexAsCoach(IndexAsCoachRequest $request): View
    {
        $filters = $request->validated();
        $filter = $filters['filter'] ?? 'upcoming';
        $studentId = $filters['student'] ?? null;
        $enrollmentId = $filters['enrollment'] ?? null;

        $query = Meeting::query()
            ->with('enrollment.certification', 'student')
            ->forCoach($request->user())
            ->when($studentId, fn ($q, $id) => $q->where('student_id', $id))
            ->when($enrollmentId, fn ($q, $id) => $q->where('enrollment_id', $id));

        $meetings = match ($filter) {
            'past' => $query->past()->orderByDesc('scheduled_at')->paginate(20),
            'all' => $query->orderByDesc('scheduled_at')->paginate(20),
            default => $query->upcoming()->orderBy('scheduled_at')->paginate(20),
        };

        return view('meeting.coach.index', [
            'meetings' => $meetings,
            'filter' => $filter,
            'studentFilter' => $studentId,
            'enrollmentFilter' => $enrollmentId,
        ]);
    }

    /**
     * 面談詳細(当事者共通)。Policy で coach/student の閲覧範囲を絞る。
     */
    public function show(Meeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $meeting->loadMissing([
            'enrollment.certification',
            'coach',
            'student',
            'canceledBy',
            'meetingMemo',
        ]);

        return view('meeting.show', [
            'meeting' => $meeting,
        ]);
    }

    /**
     * 予約画面(受講生): URL に Enrollment を含む正規ルートで表示する。
     */
    public function create(Enrollment $enrollment, MeetingQuotaService $meetingQuota): View
    {
        $this->authorize('create', Meeting::class);
        abort_unless($enrollment->user_id === auth()->id(), 403);
        abort_unless($enrollment->status === EnrollmentStatus::Learning, 403);

        $enrollment->loadMissing('certification');

        return view('meeting.create', [
            'enrollment' => $enrollment,
            'meetingsRemaining' => $meetingQuota->remaining(auth()->user()),
        ]);
    }

    /**
     * 予約画面のエントリポイント(URL に Enrollment 無し)。
     */
    public function createFallback(): View
    {
        $user = auth()->user();

        $enrollments = $user
            ?->enrollments()
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->with('certification')
            ->get();

        return view('meeting.empty-state', [
            'enrollments' => $enrollments ?? collect(),
        ]);
    }

    /**
     * 受講生の予約申請。残面談回数を確認し、空き枠から過去実績最少のコーチを自動割当して reserved で確定する。
     */
    public function store(
        Enrollment $enrollment,
        StoreRequest $request,
        MeetingAvailabilityService $availabilityService,
        CoachMeetingLoadService $coachLoadService,
        MeetingQuotaService $quotaService,
        ConsumeQuotaAction $consumeAction,
        GoogleCalendarService $googleCalendarService,
    ): RedirectResponse {
        $scheduledAt = Carbon::parse($request->validated('scheduled_at'));
        $topic = $request->validated('topic');
        $student = $enrollment->user;

        $meeting = DB::transaction(function () use (
            $enrollment,
            $student,
            $scheduledAt,
            $topic,
            $availabilityService,
            $coachLoadService,
            $quotaService,
            $consumeAction,
            $googleCalendarService,
        ) {
            if ($quotaService->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            $availabilityService->validateSlot($enrollment->certification, $scheduledAt);

            $candidates = $this->findAvailableCoaches($enrollment->certification, $scheduledAt, $googleCalendarService);

            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }

            $coach = $coachLoadService->leastLoadedCoach($candidates);

            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id,
                    'coach_id' => $coach->id,
                    'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => MeetingStatus::Reserved->value,
                    'topic' => $topic,
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                throw new MeetingNoAvailableCoachException($e);
            }

            $transaction = ($consumeAction)($student, $meeting->id);

            $meeting->update(['meeting_quota_transaction_id' => $transaction->id]);

            return $meeting->fresh();
        });

        // Google カレンダーへ面談予定を自動登録（フォールバック付き）
        $googleEventId = $googleCalendarService->createMeetingEvent($meeting->coach, $meeting);

        if ($googleEventId !== null) {
            $meeting->update(['google_event_id' => $googleEventId]);
        }

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談を予約しました。');
    }

    /**
     * 当事者(受講生 or コーチ)による面談キャンセル。
     */
    public function cancel(
        Meeting $meeting,
        RefundQuotaAction $refundAction,
        GoogleCalendarService $googleCalendarService,
    ): RedirectResponse {
        $this->authorize('cancel', $meeting);
        $actor = auth()->user();

        DB::transaction(function () use ($meeting, $actor, $refundAction) {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($locked->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $locked->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);

            ($refundAction)($locked->student, $locked->id);
        });

        // Google カレンダーの予定を連動削除（フォールバック付き）
        if (! empty($meeting->google_event_id)) {
            $googleCalendarService->deleteMeetingEvent($meeting->coach, $meeting->google_event_id);
        }

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談をキャンセルしました。面談回数を返却しました。');
    }

    /**
     * 担当コーチによる面談メモ作成・更新。
     */
    public function upsertMemo(Meeting $meeting, UpsertMemoRequest $request): RedirectResponse
    {
        $body = $request->validated('body');

        DB::transaction(function () use ($meeting, $body) {
            if (! in_array($meeting->status, [MeetingStatus::Reserved, MeetingStatus::Completed], true)) {
                throw MeetingStatusTransitionException::forMemo();
            }

            MeetingMemo::updateOrCreate(
                ['meeting_id' => $meeting->id],
                ['body' => $body],
            );
        });

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談メモを保存しました。');
    }

    /**
     * 予約画面が呼ぶ空き枠取得 JSON エンドポイント。
     */
    public function fetchAvailability(Enrollment $enrollment, AvailabilityRequest $request, MeetingAvailabilityService $availabilityService): JsonResponse
    {
        $date = Carbon::parse($request->validated('date'));

        $slots = $availabilityService->slotsForCertification(
            $enrollment->loadMissing('certification')->certification,
            $date,
        );

        return response()->json([
            'date' => $date->toDateString(),
            'slots' => $slots->map(fn (array $slot) => [
                'slot_start' => $slot['slot_start']->toIso8601String(),
                'slot_end' => $slot['slot_end']->toIso8601String(),
                'available_coach_count' => $slot['available_coach_count'],
            ])->all(),
        ]);
    }

    /**
     * 担当コーチ集合のうち、(1) 有効な availability 枠があり、(2) LMS 予約を持たず、(3) Google カレンダー予定とも重複しないコーチ集合を返す。
     *
     * @return Collection<int, User>
     */
    private function findAvailableCoaches(
        Certification $certification,
        Carbon $scheduledAt,
        GoogleCalendarService $googleCalendarService
    ): Collection {
        $time = $scheduledAt->format('H:i:s');

        $coaches = $certification->coaches()
            ->whereHas('coachAvailabilities', function ($q) use ($scheduledAt, $time) {
                $q->where('day_of_week', $scheduledAt->dayOfWeek)
                    ->where('is_active', true)
                    ->where('start_time', '<=', $time)
                    ->where('end_time', '>=', $scheduledAt->copy()->addHour()->format('H:i:s'));
            })
            ->whereDoesntHave('meetingsAsCoach', function ($q) use ($scheduledAt) {
                $q->where('scheduled_at', $scheduledAt)
                    ->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value]);
            })
            ->get();

        $endAt = $scheduledAt->copy()->addHour();

        return $coaches->filter(function (User $coach) use ($scheduledAt, $endAt, $googleCalendarService) {
            $busySlots = $googleCalendarService->getBusySlots($coach, $scheduledAt, $endAt);

            foreach ($busySlots as $busy) {
                $busyStart = Carbon::parse($busy['start']);
                $busyEnd = Carbon::parse($busy['end']);

                if ($scheduledAt->lt($busyEnd) && $endAt->gt($busyStart)) {
                    return false;
                }
            }

            return true;
        });
    }
}