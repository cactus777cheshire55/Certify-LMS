<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword implements ShouldQueue
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

    public function __construct($token)
    {
        parent::__construct($token);

        // DBトランザクションコミット後にキュー投入する
        $this->afterCommit();
    }

    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Certify LMS パスワード再設定のご案内')
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line('パスワード再設定のリクエストを受け付けました。')
            ->action('パスワードを再設定する', $url)
            ->line('このリンクは ' . config('auth.passwords.users.expire') . ' 分間有効です。')
            ->line('心当たりがない場合は、このメールを破棄してください。')
            ->salutation('Certify LMS 運営チーム');
    }
}