<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use App\Models\AiChatConversation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');
        return $conversation instanceof AiChatConversation
            && $this->user()?->can('update', $conversation);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
        ];
    }
}