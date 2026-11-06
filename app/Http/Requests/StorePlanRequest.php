<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    /**
     * リクエストの認可判定
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Plan::class) ?? false;
    }

    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'default_meeting_quota' => ['required', 'integer', 'min:0', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0','max:255'],
        ];
    }

    /**
     * 項目名の和名表示（エラーメッセージ用）
     */
    public function attributes(): array
    {
        return [
            'name' => 'プラン名',
            'description' => '説明',
            'duration_days' => '受講期間（日）',
            'default_meeting_quota' => '規定面談回数',
            'sort_order' => '並び順',
        ];
    }
}
