<?php

declare(strict_types=1);

namespace App\UseCases\QuestionCategory;

use App\Exceptions\Content\QuestionCategoryInUseException;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DestroyAction
{
    /**
     * 出題分野マスタの物理削除処理
     * 演習問題(SectionQuestion) または 模試問題(mock_exam_questions) が 1 件でも参照されていれば拒否
     *
     * @throws QuestionCategoryInUseException
     */
    public function __invoke(QuestionCategory $category): void
    {
        $sectionQuestionCount = SectionQuestion::where('category_id', $category->id)->count();

        $mockExamQuestionCount = Schema::hasTable('mock_exam_questions')
            ? DB::table('mock_exam_questions')
                ->where('category_id', $category->id)
                ->count()
            : 0;

        if ($sectionQuestionCount > 0 || $mockExamQuestionCount > 0) {
            throw new QuestionCategoryInUseException;
        }

        DB::transaction(fn () => $category->delete());
    }
}
