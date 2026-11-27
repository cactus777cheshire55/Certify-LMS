<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard;

use App\Models\User;
use App\Services\EnrollmentStatsService;
use App\UseCases\Dashboard\ViewModels\AdminDashboardViewModel;

/**
 * 管理者ダッシュボードの ViewModel を組み立てる Action。
 *
 * 全体 KPI および資格別修了率の集計結果は EnrollmentStatsService 側でキャッシュ化されている。
 */
final class FetchAdminDashboardAction
{
    use HasDashboardSafeFetch;

    public function __construct(
        private readonly EnrollmentStatsService $stats,
    ) {}

    public function __invoke(User $admin): AdminDashboardViewModel
    {
        $kpi = $this->safe(fn () => $this->stats->adminKpi());

        $completionRateByCertification = $this->safe(fn () => $this->stats->completionRateByCertification());

        $byCertificationTop10 = $kpi !== null
            ? collect($kpi['by_certification'])->take(10)
            : collect();

        $isEmptyState = $kpi === null
            ? true
            : ($kpi['learning_count'] + $kpi['passed_count'] + $kpi['failed_count'] === 0);

        return new AdminDashboardViewModel(
            kpi: $kpi,
            byCertificationTop10: $byCertificationTop10,
            completionRateByCertification: $completionRateByCertification,
            isEmptyState: $isEmptyState,
        );
    }
}
