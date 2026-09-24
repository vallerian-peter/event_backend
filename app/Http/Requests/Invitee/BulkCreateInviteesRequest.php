<?php

namespace App\Http\Requests\Invitee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating bulk Invitee creation.
 */
class BulkCreateInviteesRequest extends FormRequest
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
            'invitees' => ['required', 'array', 'min:1'],
            'invitees.*.fullName' => ['required', 'string', 'max:255'],
            'invitees.*.title' => ['nullable', 'string', 'max:220'],
            'invitees.*.email' => ['nullable', 'email', 'max:255'],
            'invitees.*.normalPhone' => ['nullable', 'string', 'max:30'],
            'invitees.*.whatsappPhone' => ['nullable', 'string', 'max:30'],
            'invitees.*.status' => ['nullable', 'string', 'in:active,pending,confirmed,declined,attended'],
            'invitees.*.division' => ['nullable', 'string', 'in:single,double,both'],
            'invitees.*.pledgeAmount' => ['nullable', 'numeric', 'min:0'],
            'invitees.*.paidAmount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
