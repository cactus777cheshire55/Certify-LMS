<?php

declare(strict_types=1);

namespace App\UseCases\MockExamSession;

use App\Enums\MockExamSessionStatus;
use App\Models\MockExamSession;

/**
 * 提出された MockExamSession の個別解答を採点し、
 * 得点率（0〜100%）・合否判定・ステータス更新を行う Action。
 */
final class GradeAction
{
    public function __invoke(MockExamSession $session): MockExamSession
    {
        // 1. 各解答の採点と正解数のカウント
        $answers = $session->answers()->with('selectedOption')->get();
        $totalCorrect = 0;

        foreach ($answers as $answer) {
            $isCorrect = $answer->selectedOption?->is_correct ?? false;
            $answer->update([
                'is_correct' => $isCorrect,
            ]);

            if ($isCorrect) {
                $totalCorrect++;
            }
        }

        // 2. 得点率の計算（0〜100 の百分率スケール、小数第2位まで保持）
        // 修正前: ($totalCorrect / $totalQuestions) -> 0.80 (0〜1スケール)
        // 修正後: round(($totalCorrect / $totalQuestions) * 100, 2) -> 80.00 (0〜100スケール)
        $totalQuestions = $session->total_questions;
        $scorePercentage = $totalQuestions > 0
            ? round(($totalCorrect / $totalQuestions) * 100, 2)
            : 0.00;

        // 3. 合格判定（0〜100 スケールの合格基準点と比較）
        $pass = $scorePercentage >= $session->passing_score_snapshot;

        // 4. セッション結果の保存・ステータス更新
        $session->update([
            'status' => MockExamSessionStatus::Graded->value,
            'graded_at' => now(),
            'total_correct' => $totalCorrect,
            'score_percentage' => $scorePercentage,
            'pass' => $pass,
        ]);

        return $session;
    }
}