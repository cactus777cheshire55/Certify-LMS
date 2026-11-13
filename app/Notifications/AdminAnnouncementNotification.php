<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminAnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Announcement $announcement
    ) {}

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
