<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MeetingPackStatus;
use App\Enums\UserRole;
use App\Models\MeetingPack;
use App\Models\User;

class MeetingPackPolicy
{
    /**
     * 一覧・詳細閲覧・作成・更新権限（管理者のみ）
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * 削除権限の判定
     */
    public function delete(User $user, MeetingPack $meetingPack): bool
    {
        // 管理者であり、かつ「公開中(Published)」ではない場合のみ削除を許可
        return $user->role === UserRole::Admin
            && $meetingPack->status !== MeetingPackStatus::Published;
    }

    public function publish(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin && $meetingPack->status === MeetingPackStatus::Draft;
    }

    public function archive(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin && $meetingPack->status === MeetingPackStatus::Published;
    }

    public function unarchive(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin && $meetingPack->status === MeetingPackStatus::Archived;
    }
}
