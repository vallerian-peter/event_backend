<?php

namespace App\Http\Requests\Committee;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommitteeMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:220'],
            'normalPhone' => ['nullable', 'string', 'max:40'],
            'whatsappPhone' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'customRole' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string', 'in:active,inactive,removed'],
        ];
    }
}
