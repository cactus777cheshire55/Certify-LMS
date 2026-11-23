<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public Announcement $announcement
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【お知らせ】'.$this->announcement->title)
            ->line($this->announcement->body)
            ->action('お知らせを確認する', route('notifications.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
            'notification_type' => 'admin_announcement',
            'announcement_id' => $this->announcement->id,
        ];
    }
}
