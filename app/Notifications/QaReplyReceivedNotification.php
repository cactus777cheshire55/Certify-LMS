<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QaReply;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QaReplyReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QaReply $reply
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $thread = $this->reply->qaThread;

        return (new MailMessage)
            ->subject('【質問掲示板】あなたの質問に回答が届きました')
            ->greeting("{$notifiable->name} 様")
            ->line("質問「{$thread->title}」に新しい回答が投稿されました。")
            ->line("回答者: {$this->reply->user->name}")
            ->action('回答を確認する', route('qa-board.show', $thread));
    }

    // ★ ここに記述します
    public function toArray(object $notifiable): array
    {
        $thread = $this->reply->qaThread;

        return [
            'notification_type' => 'qa_reply_received',
            'title' => '質問への回答届く',
            'message' => "「{$thread->title}」に {$this->reply->user->name} さんが回答しました。",
            'url' => route('qa-board.show', $thread),
            'qa_thread_id' => $thread->id,
            'qa_reply_id' => $this->reply->id,
        ];
    }
}
