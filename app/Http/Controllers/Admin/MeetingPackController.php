<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MeetingPackStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeetingPackRequest;
use App\Http\Requests\UpdateMeetingPackRequest;
use App\Models\MeetingPack;
use App\Models\Payment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class MeetingPackController extends Controller
{
    use AuthorizesRequests;

    /**
     * 面談パック一覧表示（キーワード検索・状態フィルタ・ページネーション）
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', MeetingPack::class);

        $keyword = $request->query('keyword', '');
        $status = $request->query('status', '');

        $query = MeetingPack::query();

        if (class_exists(Payment::class)) {
            $query->withCount('payments');
        }

        if ($keyword !== '') {
            $query->where('name', 'like', '%'.$keyword.'%');
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $plans = $query->orderBy('sort_order')
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('meeting-pack.management.index', compact('plans', 'keyword', 'status'));
    }

    /**
     * 新規作成画面
     */
    public function create()
    {
        $this->authorize('create', MeetingPack::class);

        return view('meeting-pack.management.create');
    }

    /**
     * 新規保存（初期状態は「下書き」）
     */
    public function store(StoreMeetingPackRequest $request)
    {
        $validated = $request->validated();

        $userId = $request->user()->id;

        $meetingPack = MeetingPack::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'meeting_count' => $validated['meeting_count'],
            'price' => $validated['price'],
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => MeetingPackStatus::Draft,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);

        return redirect()->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パックを下書きとして保存しました。');
    }

    /**
     * 詳細表示
     */
    public function show(MeetingPack $meetingPack)
    {
        $this->authorize('view', $meetingPack);

        $meetingPack->load(['createdBy', 'updatedBy']);

        if (class_exists(Payment::class)) {
            $meetingPack->load(['payments' => function ($q) {
                $q->with('user')->latest('paid_at')->take(20);
            }]);
        }

        return view('meeting-pack.management.show', [
            'plan' => $meetingPack,
        ]);
    }

    /**
     * 編集画面表示
     */
    public function edit(MeetingPack $meetingPack)
    {
        $this->authorize('update', $meetingPack);

        return view('meeting-pack.management.edit', [
            'plan' => $meetingPack,
        ]);
    }

    /**
     * 基本情報の更新
     */
    public function update(UpdateMeetingPackRequest $request, MeetingPack $meetingPack)
    {
        $validated = $request->validated();

        $meetingPack->update(array_merge($validated, [
            'updated_by_user_id' => $request->user()->id,
        ]));

        return redirect()->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パック情報を更新しました。');
    }

    /**
     * 物理削除
     */
    public function destroy(MeetingPack $meetingPack)
    {
        $this->authorize('delete', $meetingPack);

        $meetingPack->delete();

        return redirect()->route('admin.meeting-packs.index')
            ->with('success', '面談パックを削除しました。');
    }

    /**
     * 公開する
     */
    public function publish(Request $request, MeetingPack $meetingPack)
    {
        $this->authorize('publish', $meetingPack);

        $meetingPack->update([
            'status' => MeetingPackStatus::Published,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パックを公開しました。');
    }

    /**
     * アーカイブする
     */
    public function archive(Request $request, MeetingPack $meetingPack)
    {
        $this->authorize('archive', $meetingPack);

        $meetingPack->update([
            'status' => MeetingPackStatus::Archived,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パックをアーカイブしました。');
    }

    /**
     * 下書きへ戻す
     */
    public function unarchive(Request $request, MeetingPack $meetingPack)
    {
        $this->authorize('unarchive', $meetingPack);

        $meetingPack->update([
            'status' => MeetingPackStatus::Draft,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パックを下書きへ戻しました。');
    }
}
