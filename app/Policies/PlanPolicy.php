<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlanStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * 削除権限: 管理者 かつ 「下書き(Draft)」 かつ 「受講者が未紐づき(users_count === 0)」の場合のみ許可
     */
    public function delete(User $user, Plan $plan): bool
    {
        if ($user->role !== UserRole::Admin) {
            return false;
        }

        // 受講者の紐づき確認（users リレーション）
        $hasUsers = $plan->users()->exists();

        return $plan->status === PlanStatus::Draft && ! $hasUsers;
    }

    public function publish(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin && $plan->status === PlanStatus::Draft;
    }

    public function archive(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin && $plan->status === PlanStatus::Published;
    }

    public function unarchive(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin && $plan->status === PlanStatus::Archived;
    }
}
