<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;

class QaBoardSeeder extends Seeder
{
    public function run(): void
    {
        // 1. テスト用の受講生・コーチ・資格の取得
        $students = User::where('role', UserRole::Student)->get();
        $coaches = User::where('role', UserRole::Coach)->get();
        $publishedCertifications = Certification::where('status', CertificationStatus::Published)->get();

        if ($students->isEmpty() || $publishedCertifications->isEmpty()) {
            return;
        }

        // 動作確認用固定受講生（「自分の質問」一覧や解決マーク検証用）
        $targetStudent = $students->first();

        foreach ($publishedCertifications as $certification) {
            // 固定受講生による未解決スレッド
            $myThread = QaThread::create([
                'user_id' => $targetStudent->id,
                'certification_id' => $certification->id,
                'title' => "【{$certification->name}】学習方法に関する質問です",
                'body' => '教材の演習問題でつまずいています。どのように復習を進めるのが効果的でしょうか？',
                'status' => QaThreadStatus::Unresolved,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]);

            // コーチからの回答を1件付与
            if ($coaches->isNotEmpty()) {
                QaReply::create([
                    'qa_thread_id' => $myThread->id,
                    'user_id' => $coaches->first()->id,
                    'body' => 'まずは該当セクションの解説を読み込み、苦手分野ドリルで反復演習するのがおすすめです！',
                    'created_at' => now()->subDays(2),
                    'updated_at' => now()->subDays(2),
                ]);
            }

            // 他の受講生による解決済スレッド
            $otherStudent = $students->skip(1)->first() ?? $targetStudent;
            $resolvedThread = QaThread::create([
                'user_id' => $otherStudent->id,
                'certification_id' => $certification->id,
                'title' => "【{$certification->name}】用語の定義について",
                'body' => '用語Aと用語Bの違いがわかりません。区別するポイントを教えてください。',
                'status' => QaThreadStatus::Resolved,
                'resolved_at' => now()->subDay(),
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDay(),
            ]);

            // 回答（複数件）
            QaReply::create([
                'qa_thread_id' => $resolvedThread->id,
                'user_id' => $targetStudent->id,
                'body' => '用語Aは概要を指し、用語Bは具体的な手順を指すと理解すると分かりやすいですよ。',
                'created_at' => now()->subDays(4),
            ]);

            if ($coaches->isNotEmpty()) {
                QaReply::create([
                    'qa_thread_id' => $resolvedThread->id,
                    'user_id' => $coaches->first()->id,
                    'body' => 'その理解で完璧です！補足すると実際の試験でもその視点が問われます。',
                    'created_at' => now()->subDay(),
                ]);
            }

            // 回答数0件の未解決スレッド
            QaThread::create([
                'user_id' => $otherStudent->id,
                'certification_id' => $certification->id,
                'title' => "【{$certification->name}】未回答の質問サンプル",
                'body' => '回答がまだついていない質問の動作確認用データです。',
                'status' => QaThreadStatus::Unresolved,
                'created_at' => now()->subHours(5),
                'updated_at' => now()->subHours(5),
            ]);
        }
    }
}
