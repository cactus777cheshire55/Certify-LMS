<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ChatMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * 最大試行回数
     */
    public int $tries = 3;

    /**
     * リトライ間隔（秒）
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public ChatMessage $chatMessage
    ) {
        // DBトランザクションコミット後にキュー投入する
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $room = $this->chatMessage->chatRoom;
        $certName = $room->enrollment->certification->name;

        return (new MailMessage)
            ->subject("【チャット】{$certName} で新しいメッセージが届きました")
            ->greeting("{$notifiable->name} 様")
            ->line("{$this->chatMessage->sender->name} さんから新着メッセージがあります。")
            ->action('チャットを開く', route('chat.show', $room));
    }

    public function toArray(object $notifiable): array
    {
        $room = $this->chatMessage->chatRoom;

        return [
            'notification_type' => 'chat_message_received',
            'title' => '新着チャットメッセージ',
            'message' => "{$this->chatMessage->sender->name}: ".Str::limit($this->chatMessage->body, 50),
            'url' => route('chat.show', $room),
            'chat_room_id' => $room->id,
            'chat_message_id' => $this->chatMessage->id,
        ];
    }
}
