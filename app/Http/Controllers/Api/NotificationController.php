<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * 認証ユーザー本人の通知一覧を取得（JSON）
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 管理者（Admin）の API 直叩きを 403 で拒否
        if ($user->role === UserRole::Admin) {
            abort(403, '管理者は通知機能の対象外です。');
        }

        $tab = $request->query('tab', 'all');

        $query = $user->notifications();

        if ($tab === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query->take(20)->get();
        $unreadCount = $user->unreadNotifications()->count();

        $data = $notifications->map(function (DatabaseNotification $notification) {
            $notificationData = is_array($notification->data) ? $notification->data : [];

            $url = $notificationData['url'] ?? null;
            if (! $url && ($notificationData['notification_type'] ?? '') === 'admin_announcement') {
                $url = route('notifications.show', $notification);
            }
            if (! $url) {
                $url = route('notifications.index');
            }

            return [
                'id' => $notification->id,
                'title' => $notificationData['title'] ?? '通知',
                'message' => $notificationData['message'] ?? ($notificationData['body'] ?? ($notificationData['body_preview'] ?? '')),
                'url' => $url,
                'read_at' => $notification->read_at?->toIso8601String(),
                'is_unread' => $notification->unread(),
                'created_at_human' => $notification->created_at?->diffForHumans() ?? '',
            ];
        });

        return response()->json([
            'notifications' => $data,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * 単一通知の既読化（JSON）
     */
    public function markAsRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        $user = $request->user();

        // 管理者（Admin）の API 直叩きを 403 で拒否
        if ($user->role === UserRole::Admin) {
            abort(403, '管理者は通知機能の対象外です。');
        }

        // 他人の通知へのアクセスを拒否 (403)
        if ($notification->notifiable_id !== $user->id || $notification->notifiable_type !== get_class($user)) {
            abort(403, 'この操作を行う権限がありません。');
        }

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => '通知を既読にしました。',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * 全件既読化（JSON）
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        // 管理者（Admin）の API 直叩きを 403 で拒否
        if ($user->role === UserRole::Admin) {
            abort(403, '管理者は通知機能の対象外です。');
        }

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'すべての通知を既読にしました。',
            'unread_count' => 0,
        ]);
    }
}
