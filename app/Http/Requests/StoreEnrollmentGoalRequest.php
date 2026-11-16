<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentGoalRequest extends FormRequest
{
    /**
     * リクエストの認可（Policyに委譲）
     */
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $this->user()->can('create', [EnrollmentGoal::class, $enrollment]);
    }

    /**
     * 新規作成時のバリデーションルール
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
