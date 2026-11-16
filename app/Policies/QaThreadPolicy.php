<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\QaThread;
use App\Models\User;

class QaThreadPolicy
{
    /**
     * 一覧の閲覧許可（フィルタリング自体は Controller で実施）
     */
    public function viewAny(User $user): bool
    {
        // 管理者・コーチは許可
        if ($user->role === UserRole::Admin || $user->role === UserRole::Coach) {
            return true;
        }

        // 受講生は「受講中(in_progress)」のみ許可
        // 卒業生(graduated)や退会生は false となり、自動的に 403.blade.php へ遷移
        if ($user->role === UserRole::Student) {
            return $user->status === UserStatus::InProgress;
        }

        return false;
    }

    /**
     * スレッド詳細の閲覧許可
     */
    public function view(User $user, QaThread $thread): bool
    {
        // 管理者は公開停止中の資格を含め全スレッド閲覧可能
        if ($user->role === UserRole::Admin) {
            return true;
        }

        // 受講生の場合は「受講中(in_progress)」のみ許可（卒業生・退会生は拒否）
        if ($user->role === UserRole::Student) {
            return $user->status === UserStatus::InProgress;
        }

        if ($user->role === UserRole::Coach) {
            return $user->assignedCertifications()
                ->where('certifications.id', $thread->certification_id)
                ->exists();
        }

        $certification = $thread->certification;

        // 公開停止中の資格は受講生・コーチには非表示
        if (! $certification || $certification->status !== CertificationStatus::Published) {
            return false;
        }

        // 受講生は公開済資格であれば閲覧可能
        if ($user->role === UserRole::Student) {
            return true;
        }

        // コーチは担当資格のスレッドのみ閲覧可能
        if ($user->role === UserRole::Coach) {
            return $user->assignedCertifications()
                ->where('certifications.id', $certification->id)
                ->exists();
        }

        return false;
    }

    /**
     * 質問スレッド作成許可（受講生のみ）
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Student
            && $user->status === UserStatus::InProgress;
    }

    /**
     * 質問スレッドの編集許可（投稿者本人のみ）
     */
    public function update(User $user, QaThread $thread): bool
    {
        return $thread->user_id === $user->id;
    }

    /**
     * 質問スレッドの削除許可（投稿者本人 または 管理者モデレーション）
     */
    public function delete(User $user, QaThread $thread): bool
    {
        return $thread->user_id === $user->id || $user->role === UserRole::Admin;
    }

    /**
     * 解決済マークへの変更許可（投稿者本人のみ）
     */
    public function resolve(User $user, QaThread $thread): bool
    {
        return $thread->user_id === $user->id;
    }

    /**
     * 未解決状態への復元許可（投稿者本人のみ）
     */
    public function unresolve(User $user, QaThread $thread): bool
    {
        return $thread->user_id === $user->id;
    }
}
