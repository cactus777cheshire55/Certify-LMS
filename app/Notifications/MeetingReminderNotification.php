<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Meeting $meeting,
        public string $window // 'eve' または 'one_hour_before'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $timingLabel = $this->window === 'eve' ? '明日' : '1時間後';
        $timeStr = $this->meeting->scheduled_at->format('Y年n月j日 H:i');

        return (new MailMessage)
            ->subject("【リマインダー】{$timingLabel}に面談が予定されています")
            ->line("{$timingLabel}（{$timeStr}）に面談が予定されています。")
            ->line('相談内容: ' . $this->meeting->topic)
            ->action('面談詳細を確認する', route('meetings.show', $this->meeting));
    }

    public function toArray(object $notifiable): array
    {
        $timingLabel = $this->window === 'eve' ? '明日' : '1時間後';

        return [
            'title' => "【リマインダー】{$timingLabel}の面談通知",
            'body' => "{$timingLabel}（" . $this->meeting->scheduled_at->format('H:i') . "〜）に面談が予定されています。",
            'notification_type' => 'meeting_reminder',
            'meeting_id' => $this->meeting->id,
            'url' => route('meetings.show', $this->meeting),
        ];
    }
}