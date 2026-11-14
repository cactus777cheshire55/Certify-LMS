<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EnrollmentNote;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $this->user()->can('create', [EnrollmentNote::class, $enrollment]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => '新規メモ',
        ];
    }
}
