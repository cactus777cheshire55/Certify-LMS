<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

class EnrollmentGoalPolicy
{
    /**
     * 目標作成権限（受講生本人のみ）
     */
    public function create(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student && $user->id === $enrollment->user_id;
    }

    /**
     * 目標更新権限（受講生本人のみ）
     */
    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $user->role === UserRole::Student && $user->id === $goal->enrollment->user_id;
    }

    /**
     * 目標削除権限（受講生本人のみ）
     */
    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $user->role === UserRole::Student && $user->id === $goal->enrollment->user_id;
    }

    /**
     * 達成マーク権限（受講生本人のみ）
     */
    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $user->role === UserRole::Student && $user->id === $goal->enrollment->user_id;
    }

    /**
     * 達成解除権限（受講生本人のみ）
     */
    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $user->role === UserRole::Student && $user->id === $goal->enrollment->user_id;
    }
}
