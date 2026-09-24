<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class BroadcastMessageRequest extends FormRequest
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
            'channel' => ['required', 'string', 'in:sms,whatsapp'],
            'message' => ['required', 'string', 'max:1000'],
            'templateName' => ['nullable', 'string', 'max:255'],
            'placeholders' => ['nullable', 'array'],
            'division' => ['nullable', 'string', 'in:single,double,both'],
            'status' => ['nullable', 'string', 'in:active,confirmed,declined,cancelled,attended'],
            'contributionStatus' => ['nullable', 'string', 'in:pending,paid,refunded'],
            'inviteeIds' => ['nullable', 'array'],
            'inviteeIds.*' => ['integer', 'exists:invitees,id'],
        ];
    }
}
