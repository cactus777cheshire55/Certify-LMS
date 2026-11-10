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

        $notifications = $query->paginate(20)->withQueryString();
        $unreadCount = $user->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'unreadCount', 'tab'));
    }

    /**
     * 通知詳細表示（自分宛の通知のみ・自動既読化）
     */
    public function show(Request $request, DatabaseNotification $notification): View
    {
        // 他人宛の通知へのアクセス防止
        if ($notification->notifiable_id !== $request->user()->id) {
            abort(403);
        }

        // 詳細画面を開いた際に未読であれば既読化
        if ($notification->unread()) {
            $notification->markAsRead();
        }

        return view('notifications.show', compact('notification'));
    }

    /**
     * 単一通知の既読化と画面遷移（直リンク・ダイレクト遷移用）
     */
    public function markAsRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        if ($notification->notifiable_id !== $request->user()->id) {
            abort(403);
        }

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        // 1. data['url'] が明示的に設定されている場合はそのURLへ
        if (! empty($notification->data['url'])) {
            return redirect()->to($notification->data['url']);
        }

        // 2. 全体お知らせ通知の場合は詳細画面（notifications.show）へ遷移
        if (($notification->data['notification_type'] ?? '') === 'admin_announcement') {
            return redirect()->route('notifications.show', $notification);
        }

        // 3. それ以外は通知一覧へ
        return redirect()->route('notifications.index');
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
