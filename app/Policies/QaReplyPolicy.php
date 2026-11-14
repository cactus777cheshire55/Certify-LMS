<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

class QaReplyPolicy
{
    /**
     * 回答の投稿許可（受講生またはコーチ、管理者は投稿不可）
     */
    public function create(User $user, QaThread $thread): bool
    {
        // 管理者は回答不可
        if ($user->role === UserRole::Admin) {
            return false;
        }

        $certification = $thread->certification;
        if (! $certification || $certification->status !== CertificationStatus::Published) {
            return false;
        }

        if ($user->role === UserRole::Student) {
            return $user->status === UserStatus::InProgress;
        }

        if ($user->role === UserRole::Coach) {
            return $user->assignedCertifications()
                ->where('certifications.id', $certification->id)
                ->exists();
        }

        return false;
    }

    /**
     * 回答の編集許可（回答投稿者本人のみ）
     */
    public function update(User $user, QaReply $reply): bool
    {
        return $reply->user_id === $user->id;
    }

    /**
     * 回答の削除許可（回答投稿者本人 または 管理者モデレーション）
     */
    public function delete(User $user, QaReply $reply): bool
    {
        return $reply->user_id === $user->id || $user->role === UserRole::Admin;
    }
}
