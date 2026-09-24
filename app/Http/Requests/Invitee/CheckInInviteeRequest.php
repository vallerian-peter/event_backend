<?php

namespace App\Http\Requests\Invitee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request validating Invitee gate check-in.
 */
class CheckInInviteeRequest extends FormRequest
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
            'scannedCode' => ['nullable', 'string', 'max:64'],
            'gateKeeperId' => ['nullable', 'integer', 'exists:gateKeepers,id'],
            'gateName' => ['nullable', 'string', 'max:100'],
            'deviceInfo' => ['nullable', 'array'],
            'isOfflineSync' => ['nullable', 'boolean'],
        ];
    }
}
