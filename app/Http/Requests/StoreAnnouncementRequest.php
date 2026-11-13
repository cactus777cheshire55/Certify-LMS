<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => ['required', new Enum(AnnouncementTargetType::class)],
            'target_certification_id' => [
                'nullable',
                'required_if:target_type,'.AnnouncementTargetType::Certification->value,
                'exists:certifications,id',
            ],
            'target_user_id' => [
                'nullable',
                'required_if:target_type,'.AnnouncementTargetType::User->value,
                'exists:users,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'target_certification_id.required_if' => '配信対象が資格指定の場合は、対象資格を指定してください。',
            'target_user_id.required_if' => '配信対象がユーザー指定の場合は、対象受講生を指定してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => '配信対象',
            'target_certification_id' => '対象資格',
            'target_user_id' => '対象受講生',
        ];
    }
}
