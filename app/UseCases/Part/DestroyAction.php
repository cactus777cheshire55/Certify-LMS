<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentNotDeletableException;
use App\Models\LearningSession;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\SectionQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Part の Delete ユースケース。Draft 状態のみ削除可。
 */
final class DestroyAction
{
    /**
     * @throws ContentNotDeletableException
     */
    public function __invoke(Part $part): void
    {
        if ($part->status !== ContentStatus::Draft) {
            throw ContentNotDeletableException::forPart();
        }

        DB::transaction(function () use ($part) {
            // 1. 対象 Part 配下の Chapter ID 一覧を取得
            $chapterIds = $part->chapters()->pluck('id');

            if ($chapterIds->isNotEmpty()) {
                // 2. チャプター配下の Section ID 一覧を取得
                $sectionIds = Section::whereIn('chapter_id', $chapterIds)->pluck('id');

                if ($sectionIds->isNotEmpty()) {
                    // セクション配下の学習滞在セッション（ログ）を削除（外部キー制約対策）
                    LearningSession::whereIn('section_id', $sectionIds)->delete();

                    // セクション配下の進捗データを削除（外部キー制約対策）
                    // ※もしモデル名が異なる場合はプロジェクトに合わせて調整してください
                    SectionProgress::whereIn('section_id', $sectionIds)->delete();

                    // 3. セクション配下の演習問題を削除
                    SectionQuestion::whereIn('section_id', $sectionIds)->delete();

                    // 4. セクションを削除
                    Section::whereIn('id', $sectionIds)->delete();
                }

                // 5. チャプターを削除
                $part->chapters()->delete();
            }

            // 6. 最後に Part 本体を削除
            $part->delete();
        });
    }
}