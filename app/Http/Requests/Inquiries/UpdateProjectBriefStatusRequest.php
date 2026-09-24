<?php

namespace App\Http\Requests\Inquiries;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectBriefStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:new,contacted,in_discussion,converted,closed'],
            'adminNotes' => ['nullable', 'string', 'max:2000'],
            'assignedToUserId' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
