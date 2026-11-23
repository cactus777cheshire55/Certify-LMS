<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | 管理者ダッシュボード キャッシュ設定
    |--------------------------------------------------------------------------
    */
    'admin_kpi_cache_key' => 'dashboard:admin_kpi',
    'admin_completion_rate_cache_key' => 'dashboard:admin_completion_rate',
    'cache_ttl' => 3600, // 1時間 (秒指定)
];
