<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEnrollmentGoalRequest extends FormRequest
{
    /**
     * リクエストの認可（Policyに委譲）
     */
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $this->user()->can('update', $goal);
    }

    /**
     * 更新時のバリデーションルール
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'target_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => '目標',
            'target_date' => '目標期日',
            'description' => '詳細',
        ];
    }
}
