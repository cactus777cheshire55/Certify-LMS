<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ログインユーザー本人の操作のため常に true
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];

        // コーチのみ固定面談URLのバリデーションを追加
        if ($this->user()?->role === UserRole::Coach) {
            $rules['meeting_url'] = ['nullable', 'url', 'max:500'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'bio' => '自己紹介',
        ];
    }
}