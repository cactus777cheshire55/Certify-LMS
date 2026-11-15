<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

/**
 * Certificate(修了証)リソースに対する認可ポリシー。
 *
 * - download: 管理者(全件) / 当該資格の担当コーチ / 受講生本人
 *   受講生のステータス(受講中 / 卒業)は問わない(修了証は永続資産)。
 *
 * coach の判定は certification_coach_assignments(User::assignedCertifications)で行う。
 */
class CertificatePolicy
{
    public function download(User $user, Certificate $certificate): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $user->assignedCertifications()
                ->whereKey($certificate->certification_id)
                ->exists(),
            UserRole::Student => $certificate->user_id === $user->id,
        };
    }
}
