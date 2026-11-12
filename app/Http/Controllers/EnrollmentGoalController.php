<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentGoalRequest;
use App\Http\Requests\UpdateEnrollmentGoalRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EnrollmentGoalController extends Controller
{
    /**
     * 目標の新規追加
     */
    public function store(StoreEnrollmentGoalRequest $request, Enrollment $enrollment): RedirectResponse
    {
        $enrollment->goals()->create($request->validated());

        return redirect()->route('enrollments.show', $enrollment)
            ->with('success', '個人目標を追加しました。');
    }

    /**
     * 目標の編集画面表示
     */
    public function edit(EnrollmentGoal $goal): View
    {
        Gate::authorize('update', $goal);

        return view('enrollment-goal.edit', compact('goal'));
    }

    /**
     * 目標の基本情報更新
     */
    public function update(UpdateEnrollmentGoalRequest $request, EnrollmentGoal $goal): RedirectResponse
    {
        $goal->update($request->validated());

        return redirect()->route('enrollments.show', $goal->enrollment_id)
            ->with('success', '個人目標を更新しました。');
    }

    /**
     * 目標の物理削除
     */
    public function destroy(EnrollmentGoal $goal): RedirectResponse
    {
        Gate::authorize('delete', $goal);

        $enrollmentId = $goal->enrollment_id;
        $goal->delete();

        return redirect()->route('enrollments.show', $enrollmentId)
            ->with('success', '個人目標を削除しました。');
    }

    /**
     * 達成マーク付与
     */
    public function markAchieved(EnrollmentGoal $goal): RedirectResponse
    {
        Gate::authorize('markAchieved', $goal);

        $goal->update(['achieved_at' => now()]);

        return redirect()->route('enrollments.show', $goal->enrollment_id)
            ->with('success', '目標を達成にしました！');
    }

    /**
     * 達成マーク解除
     */
    public function unmarkAchieved(EnrollmentGoal $goal): RedirectResponse
    {
        Gate::authorize('unmarkAchieved', $goal);

        $goal->update(['achieved_at' => null]);

        return redirect()->route('enrollments.show', $goal->enrollment_id)
            ->with('success', '目標の達成を解除しました。');
    }
}
