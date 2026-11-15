<?php

declare(strict_types=1);

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| JSON API のルート定義。
|
*/

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    // 通知 API
    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('api.v1.notifications.markAsRead');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.v1.notifications.markAllAsRead');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
