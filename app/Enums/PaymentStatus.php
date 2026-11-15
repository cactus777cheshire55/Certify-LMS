<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '決済処理中',
            self::Succeeded => '支払い完了',
            self::Failed => '決済失敗',
            self::Refunded => '返金済み',
        };
    }
}
