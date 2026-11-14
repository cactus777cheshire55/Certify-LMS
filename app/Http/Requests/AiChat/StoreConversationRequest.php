<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() && $this->user()?->isInProgress();
    }

    public function rules(): array
    {
        return [
            'section_id' => ['nullable', 'ulid', 'exists:sections,id'],
            'message' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'section_id' => '教材セクション',
            'message' => '質問',
        ];
    }
}