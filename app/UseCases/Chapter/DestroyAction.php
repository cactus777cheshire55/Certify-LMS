<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Enums\ContentStatus;
use App\Exceptions\Content\ContentNotDeletableException;
use App\Models\Chapter;
use App\Models\Section;
use App\Models\SectionImage;
use App\Models\SectionQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Chapter の Delete ユースケース。Draft 状態のみ削除可、Published は削除拒否。
 */
final class DestroyAction
{
    /**
     * @throws ContentNotDeletableException
     */
    public function __invoke(Chapter $chapter): void
    {
        if ($chapter->status !== ContentStatus::Draft) {
            throw ContentNotDeletableException::forChapter();
        }

        DB::transaction(function () use ($chapter) {
            // 1. 対象 Chapter 配下の Section ID 一覧を取得
            $sectionIds = $chapter->sections()->pluck('id');

            if ($sectionIds->isNotEmpty()) {
                // 2. セクション配下の演習問題を先に削除
                if (class_exists(SectionQuestion::class)) {
                    SectionQuestion::whereIn('section_id', $sectionIds)->delete();
                }

                // 3. セクション配下の画像データを先に削除
                if (class_exists(SectionImage::class)) {
                    SectionImage::whereIn('section_id', $sectionIds)->delete();
                }

                // 4. セクション本体を削除
                $chapter->sections()->delete();
            }

            // 5. 最後に Chapter 本体を削除
            $chapter->delete();
        });
    }
}