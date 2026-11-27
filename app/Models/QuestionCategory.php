<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class QuestionCategory extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'certification_id',
        'name',
        'slug',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * 紐づく問題（演習問題 + 模試問題）の合計件数を取得する動的アクセサ
     * Blade側で $category->questions_count が参照された際に自動的に実行されます
     */
    public function getQuestionsCountAttribute(): int
    {
        // 1. 演習問題（SectionQuestion）の紐づき件数を取得
        $sectionQuestionsCount = $this->sectionQuestions()->count();

        // 2. 模試問題（MockExamQuestion）の紐づき件数を取得（テーブルが存在する場合のみ）
        $mockExamQuestionsCount = Schema::hasTable('mock_exam_questions')
            ? $this->mockExamQuestions()->count()
            : 0;

        // 3. 合計件数を返す
        return $sectionQuestionsCount + $mockExamQuestionsCount;
    }

    /**
     * 資格マスタへのリレーション
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * 演習問題へのリレーション
     */
    public function sectionQuestions(): HasMany
    {
        return $this->hasMany(SectionQuestion::class, 'category_id');
    }

    /**
     * 模試問題へのリレーション
     */
    public function mockExamQuestions(): HasMany
    {
        return $this->hasMany(MockExamQuestion::class, 'category_id');
    }

    /**
     * 並び順スコープ
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('created_at');
    }
}
