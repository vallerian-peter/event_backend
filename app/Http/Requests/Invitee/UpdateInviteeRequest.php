<?php

namespace App\Http\Requests\Invitee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating Invitee updates.
 */
class UpdateInviteeRequest extends FormRequest
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
            'fullName' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:220'],
            'email' => ['nullable', 'email', 'max:255'],
            'normalPhone' => ['nullable', 'string', 'max:30'],
            'whatsappPhone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'in:active,pending,confirmed,declined,attended'],
            'inviteStatus' => ['nullable', 'string', 'in:active,pending,confirmed,declined,attended'],
            'division' => ['nullable', 'string', 'in:single,double,both'],
            'pledgeAmount' => ['nullable', 'numeric', 'min:0'],
            'paidAmount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
