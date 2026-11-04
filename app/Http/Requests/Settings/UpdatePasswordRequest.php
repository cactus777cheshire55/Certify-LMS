<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    // Blade側で $errors->updatePassword を参照できるエラーバッグ名
    protected $errorBag = 'updatePassword';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => ':attributeを入力してください。', 
            
            'current_password.current_password' => '入力された:attributeが現在のパスワードと一致しません。',
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => 'パスワード',
            'password' => 'パスワード',
        ];
    }
}