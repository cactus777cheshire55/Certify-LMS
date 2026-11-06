<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingStatus;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendMeetingReminders extends Command
{
    protected $signature = 'notifications:send-meeting-reminders {--window= : 配信ウィンドウ (eve または one_hour_before)}';
    protected $description = '予約済み面談の事前リマインダー通知を送信する';

    public function handle(): int
    {
        $window = $this->option('window');

        if (! in_array($window, ['eve', 'one_hour_before'], true)) {
            $this->error('無効な window オプションです。--window=eve または --window=one_hour_before を指定してください。');
            return Command::FAILURE;
        }

        $now = now();

        // 抽出クエリのビルド
        $query = Meeting::with(['student', 'coach'])
            ->where('status', MeetingStatus::Reserved->value);

        if ($window === 'eve') {
            // 前日リマインド: 翌日の面談かつ未送信
            $start = $now->copy()->addDay()->startOfDay();
            $end = $now->copy()->addDay()->endOfDay();

            $query->whereBetween('scheduled_at', [$start, $end])
                ->whereNull('reminded_eve_at');
        } else {
            // 1時間前リマインド: 開始55分〜65分後の枠かつ未送信
            $start = $now->copy()->addMinutes(55);
            $end = $now->copy()->addMinutes(65);

            $query->whereBetween('scheduled_at', [$start, $end])
                ->whereNull('reminded_one_hour_at');
        }

        $meetings = $query->get();
        $dispatchedCount = 0;

        foreach ($meetings as $meeting) {
            DB::transaction(function () use ($meeting, $window, $now, &$dispatchedCount) {
                // 送信済みフラグをロック更新（二重送信防止）
                $column = $window === 'eve' ? 'reminded_eve_at' : 'reminded_one_hour_at';
                
                $updated = Meeting::where('id', $meeting->id)
                    ->whereNull($column)
                    ->update([$column => $now]);

                if ($updated === 0) {
                    return; // 既に他プロセスで処理済み
                }

                // 配信対象当事者の抽出（アクティブユーザーのみ）
                $targets = collect([$meeting->student, $meeting->coach])
                    ->filter(fn ($user) => $user && $user->status !== UserStatus::Withdrawn);

                if ($targets->isNotEmpty()) {
                    Notification::send($targets, new MeetingReminderNotification($meeting, $window));
                    $dispatchedCount++;
                }
            });
        }

        $this->info("リマインダー配信完了 [window={$window}]: {$dispatchedCount} 件の面談に対して処理を実行しました。");

        return Command::SUCCESS;
    }
}