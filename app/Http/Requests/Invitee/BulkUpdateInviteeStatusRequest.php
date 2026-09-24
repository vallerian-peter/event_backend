<?php

namespace App\Http\Requests\Invitee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating bulk update of Invitee statuses.
 */
class BulkUpdateInviteeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'eventId' => ['required', 'integer', 'exists:events,id'],
            'inviteeIds' => ['nullable', 'array'],
            'inviteeIds.*' => ['integer', 'exists:invitees,id'],
            'status' => ['required', 'string', 'in:active,pending,confirmed,declined,attended'],
        ];
    }
}
