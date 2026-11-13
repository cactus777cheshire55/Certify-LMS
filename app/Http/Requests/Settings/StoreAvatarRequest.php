<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:2048', // 最大 2MB (2048 KB)
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'avatar' => 'アイコン画像',
        ];
    }
}
