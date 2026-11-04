<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * 通知一覧表示（全件 / 未読フィルタ）
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab', 'all');

        $query = $user->notifications();

        if ($tab === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(10)->withQueryString();
        $unreadCount = $user->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'unreadCount', 'tab'));
    }

    /**
     * 単一通知の既読化と画面遷移
     */
    public function markAsRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        // 他人宛の通知へのアクセス防止
        if ($notification->notifiable_id !== $request->user()->id) {
            abort(403);
        }

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        $targetUrl = $notification->data['url'] ?? route('notifications.index');

        return redirect()->to($targetUrl);
    }

    /**
     * 全件既読化
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->route('notifications.index')
            ->with('success', 'すべての通知を既読にしました。');
    }
}
