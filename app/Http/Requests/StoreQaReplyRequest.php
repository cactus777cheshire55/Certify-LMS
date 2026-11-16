<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\QaReply;
use Illuminate\Foundation\Http\FormRequest;

class StoreQaReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [QaReply::class, $this->route('thread')]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => '回答本文',
        ];
    }
}
