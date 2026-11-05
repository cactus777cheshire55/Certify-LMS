<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

class EnrollmentNotePolicy
{
    /**
     * メモ一覧の閲覧権限（担当コーチまたは管理者）
     */
    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Coach) {
            return $user->assignedCertifications->contains($enrollment->certification_id);
        }

        return false;
    }

    /**
     * メモの新規作成権限（担当コーチまたは管理者）
     */
    public function create(User $user, Enrollment $enrollment): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Coach) {
            return $user->assignedCertifications->contains($enrollment->certification_id);
        }

        return false;
    }

    /**
     * メモの更新権限（作成者本人または管理者）
     */
    public function update(User $user, EnrollmentNote $note): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Coach) {
            return $user->id === $note->author_id
                && $user->assignedCertifications->contains($note->enrollment->certification_id);
        }

        return false;
    }

    /**
     * メモの削除権限（作成者本人または管理者）
     */
    public function delete(User $user, EnrollmentNote $note): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Coach) {
            return $user->id === $note->author_id
                && $user->assignedCertifications->contains($note->enrollment->certification_id);
        }

        return false;
    }
}