<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingQuota;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_pack_id' => [
                'required',
                'string',
                Rule::exists('meeting_packs', 'id')->where(function ($query) {
                    $query->where('status', 'published');
                }),
            ],
        ];
    }
}
