<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\Announcement;
use App\Models\ChatMessage;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Notifications\AdminAnnouncementNotification;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingReminderNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

class QueueNotificationUnitTest extends TestCase
{
    public function test_notification_classes_implement_should_queue_interface(): void
    {
        $notificationClasses = array(
            ResetPasswordNotification::class,
            MeetingReminderNotification::class,
            QaReplyReceivedNotification::class,
            AdminAnnouncementNotification::class,
            ChatMessageReceivedNotification::class,
        );

        foreach ($notificationClasses as $className) {
            $this->assertTrue(
                is_subclass_of($className, ShouldQueue::class),
                "{$className} が ShouldQueue インターフェースを実装していません。"
            );
        }
    }

    public function test_notification_classes_have_correct_queue_configuration(): void
    {
        $notifications = array(
            new ResetPasswordNotification('dummy-token'),
            new MeetingReminderNotification(new Meeting(), 'eve'),
            new QaReplyReceivedNotification(new QaReply()),
            new AdminAnnouncementNotification(new Announcement()),
            new ChatMessageReceivedNotification(new ChatMessage()),
        );

        foreach ($notifications as $notification) {
            $className = get_class($notification);

            $this->assertTrue(
                $notification->afterCommit ?? false,
                "{$className} の \$afterCommit が true に設定されていません。"
            );
            $this->assertSame(
                3,
                $notification->tries ?? null,
                "{$className} の \$tries が 3 に設定されていません。"
            );
            $this->assertSame(
                array(10, 60, 300),
                $notification->backoff ?? null,
                "{$className} の \$backoff が正しく設定されていません。"
            );
        }
    }
}