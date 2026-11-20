<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Meeting\AvailabilityRequest;
use App\Http\Requests\Meeting\IndexAsCoachRequest;
use App\Http\Requests\Meeting\IndexRequest;
use App\Http\Requests\Meeting\StoreRequest;
use App\Http\Requests\Meeting\UpsertMemoRequest;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Services\MeetingQuotaService;
use App\UseCases\Meeting\CancelAction;
use App\UseCases\Meeting\FetchAvailabilityAction;
use App\UseCases\Meeting\IndexAction;
use App\UseCases\Meeting\IndexAsCoachAction;
use App\UseCases\Meeting\ShowAction;
use App\UseCases\Meeting\StoreAction;
use App\UseCases\Meeting\UpsertMemoAction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MeetingController extends Controller
{
    /**
     * 受講生本人の面談一覧
     */
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $data = $action($request->user(), $request->validated());

        return view('meeting.index', $data);
    }

    /**
     * コーチ宛の面談一覧
     */
    public function indexAsCoach(IndexAsCoachRequest $request, IndexAsCoachAction $action): View
    {
        $data = $action($request->user(), $request->validated());

        return view('meeting.coach.index', $data);
    }

    /**
     * 面談詳細(当事者共通)
     */
    public function show(Meeting $meeting, ShowAction $action): View
    {
        $this->authorize('view', $meeting);

        return view('meeting.show', [
            'meeting' => $action($meeting),
        ]);
    }

    /**
     * 予約画面(受講生) - ※業務ロジックを持たないため Controller に残す
     */
    public function create(Enrollment $enrollment, MeetingQuotaService $meetingQuota): View
    {
        $this->authorize('create', Meeting::class);
        abort_unless($enrollment->user_id === auth()->id(), 403);

        $enrollment->loadMissing('certification');

        return view('meeting.create', [
            'enrollment' => $enrollment,
            'meetingsRemaining' => $meetingQuota->remaining(auth()->user()),
        ]);
    }

    /**
     * 予約画面のエントリポイント - ※業務ロジックを持たないため Controller に残す
     */
    public function createFallback(): View
    {
        $user = auth()->user();
        $enrollments = $user->enrollments()->with('certification')->get();

        return view('meeting.empty-state', [
            'enrollments' => $enrollments,
        ]);
    }

    /**
     * 受講生の予約申請
     */
    public function store(
        Enrollment $enrollment,
        StoreRequest $request,
        StoreAction $action,
    ): RedirectResponse {
        $scheduledAt = Carbon::parse($request->validated('scheduled_at'));
        $topic = $request->validated('topic');

        $meeting = $action($enrollment, $scheduledAt, $topic);

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談を予約しました。');
    }

    /**
     * 当事者(受講生 or コーチ)による面談キャンセル
     */
    public function cancel(Meeting $meeting, CancelAction $action): RedirectResponse
    {
        $this->authorize('cancel', $meeting);

        $action($meeting, auth()->user());

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談をキャンセルしました。面談回数を返却しました。');
    }

    /**
     * 担当コーチによる面談メモ作成・更新
     */
    public function upsertMemo(Meeting $meeting, UpsertMemoRequest $request, UpsertMemoAction $action): RedirectResponse
    {
        $action($meeting, $request->validated('body'));

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談メモを保存しました。');
    }

    /**
     * 予約画面が呼ぶ空き枠取得 JSON エンドポイント
     */
    public function fetchAvailability(
        Enrollment $enrollment,
        AvailabilityRequest $request,
        FetchAvailabilityAction $action,
    ): JsonResponse {
        $date = Carbon::parse($request->validated('date'));

        return response()->json($action($enrollment, $date));
    }
}