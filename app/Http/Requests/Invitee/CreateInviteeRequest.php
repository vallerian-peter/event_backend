<?php

namespace App\Http\Requests\Invitee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating Invitee creation.
 */
class CreateInviteeRequest extends FormRequest
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
            'fullName' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:220'],
            'email' => ['nullable', 'email', 'max:255'],
            'normalPhone' => ['nullable', 'string', 'max:30', 'required_without:whatsappPhone'],
            'whatsappPhone' => ['nullable', 'string', 'max:30', 'required_without:normalPhone'],
            'status' => ['nullable', 'string', 'in:active,pending,confirmed,declined,attended'],
            'inviteStatus' => ['nullable', 'string', 'in:active,pending,confirmed,declined,attended'],
            'division' => ['nullable', 'string', 'in:single,double,both'],
            'pledgeAmount' => ['nullable', 'numeric', 'min:0'],
            'paidAmount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'normalPhone.required_without' => 'Please provide either a regular phone number or a WhatsApp phone number.',
            'whatsappPhone.required_without' => 'Please provide either a regular phone number or a WhatsApp phone number.',
        ];
    }
}
