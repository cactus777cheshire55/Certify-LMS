<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard;

use App\Models\User;
use App\Services\EnrollmentStatsService;
use App\UseCases\Dashboard\ViewModels\AdminDashboardViewModel;
use Illuminate\Support\Facades\Cache;

/**
 * 管理者ダッシュボードの ViewModel を組み立てる Action。
 * 
 * 全体 KPI および資格別修了率の集計処理を Cache::remember でキャッシュ化する。
 */
final class FetchAdminDashboardAction
{
    use HasDashboardSafeFetch;

    public function __construct(
        private readonly EnrollmentStatsService $stats,
    ) {}

    public function __invoke(User $admin): AdminDashboardViewModel
    {
        $cacheTtl = config('dashboard.cache_ttl', 3600);

        // 全体 KPI のキャッシュ取得・生成
        $kpi = $this->safe(fn () => Cache::remember(
            config('dashboard.admin_kpi_cache_key', 'dashboard:admin_kpi'),
            $cacheTtl,
            fn () => $this->stats->adminKpi()
        ));

        // 資格別修了率のキャッシュ取得・生成
        $completionRateByCertification = $this->safe(fn () => Cache::remember(
            config('dashboard.admin_completion_rate_cache_key', 'dashboard:admin_completion_rate'),
            $cacheTtl,
            fn () => $this->stats->completionRateByCertification()
        ));

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