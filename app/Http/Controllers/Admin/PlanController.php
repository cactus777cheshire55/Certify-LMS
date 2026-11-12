<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\Plan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    use AuthorizesRequests;

    /**
     * プラン一覧表示
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Plan::class);

        $keyword = $request->query('keyword', '');
        $status = $request->query('status', '');

        $query = Plan::query()->withCount('users');

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

        return view('plan.management.index', compact('plans', 'keyword', 'status'));
    }

    /**
     * 新規作成画面表示
     */
    public function create(): View
    {
        $this->authorize('create', Plan::class);

        return view('plan.management.create');
    }

    /**
     * 新規保存（FormRequest適用）
     */
    public function store(StorePlanRequest $request): RedirectResponse
    {
        // 認可とバリデーションは FormRequest 側で完了済み
        $validated = $request->validated();
        $userId = $request->user()->id;

        $plan = Plan::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_days' => $validated['duration_days'],
            'default_meeting_quota' => $validated['default_meeting_quota'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => PlanStatus::Draft,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);

        return redirect()->route('admin.plans.show', $plan)
            ->with('success', '受講プランを下書きとして保存しました。');
    }

    /**
     * 詳細表示
     */
    public function show(Plan $plan): View
    {
        $this->authorize('view', $plan);

        $plan->load(['users', 'createdBy', 'updatedBy']);

        return view('plan.management.show', compact('plan'));
    }

    /**
     * 編集画面表示
     */
    public function edit(Plan $plan): View
    {
        $this->authorize('update', $plan);

        return view('plan.management.edit', compact('plan'));
    }

    /**
     * 更新（FormRequest適用）
     */
    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        // 認可とバリデーションは FormRequest 側で完了済み
        $validated = $request->validated();

        $plan->update(array_merge($validated, [
            'updated_by_user_id' => $request->user()->id,
        ]));

        return redirect()->route('admin.plans.show', $plan)
            ->with('success', 'プラン情報を更新しました。');
    }

    /**
     * 削除（Policyにより公開中・受講生紐づきありの場合は403拒否）
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()->route('admin.plans.index')
            ->with('success', 'プランを削除しました。');
    }

    /**
     * 公開する（下書き → 公開中）
     */
    public function publish(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('publish', $plan);

        $plan->update([
            'status' => PlanStatus::Published,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.plans.show', $plan)
            ->with('success', 'プランを公開しました。');
    }

    /**
     * アーカイブする（公開中 → アーカイブ）
     */
    public function archive(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('archive', $plan);

        $plan->update([
            'status' => PlanStatus::Archived,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.plans.show', $plan)
            ->with('success', 'プランをアーカイブしました。');
    }

    /**
     * 下書きへ戻す（アーカイブ → 下書き）
     */
    public function unarchive(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('unarchive', $plan);

        $plan->update([
            'status' => PlanStatus::Draft,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.plans.show', $plan)
            ->with('success', 'プランを下書きへ戻しました。');
    }
}
