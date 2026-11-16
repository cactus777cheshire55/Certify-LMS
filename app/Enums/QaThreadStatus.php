<?php

declare(strict_types=1);

namespace App\Enums;

enum QaThreadStatus: string
{
    case Unresolved = 'unresolved';
    case Resolved = 'resolved';

    /**
     * 日本語表示ラベル
     */
    public function label(): string
    {
        return match ($this) {
            self::Unresolved => '未解決',
            self::Resolved => '解決済',
        };
    }

    /**
     * 画面上のステータスバッジ用カラークラス（Tailwind CSS想定）
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Unresolved => 'bg-amber-100 text-amber-800 border-amber-300',
            self::Resolved => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        };
    }
}
