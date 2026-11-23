<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class EnrollmentStatusChangeService
{
    /**
     * 受講ステータスの遷移ログを記録し、管理者ダッシュボードのキャッシュを破棄する。
     */
    public function recordStatusChange(
        Enrollment $enrollment,
        ?EnrollmentStatus $fromStatus,
        EnrollmentStatus $toStatus,
        ?User $changedBy = null,
        ?string $reason = null
    ): EnrollmentStatusLog {
        $log = EnrollmentStatusLog::create([
            'enrollment_id' => $enrollment->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by_user_id' => $changedBy?->id,
            'changed_at' => now(),
            'changed_reason' => $reason,
        ]);

        // ステータス変更時に管理者ダッシュボードのキャッシュをクリア（無効化）
        $this->invalidateDashboardCache();

        return $log;
    }

    /**
     * 管理者ダッシュボードの集計キャッシュを無効化する。
     */
    private function invalidateDashboardCache(): void
    {
        Cache::forget(config('dashboard.admin_kpi_cache_key', 'dashboard:admin_kpi'));
        Cache::forget(config('dashboard.admin_completion_rate_cache_key', 'dashboard:admin_completion_rate'));
    }
}