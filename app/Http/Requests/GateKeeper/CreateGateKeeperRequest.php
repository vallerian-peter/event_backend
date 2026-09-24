<?php

namespace App\Http\Requests\GateKeeper;

use Illuminate\Foundation\Http\FormRequest;

class CreateGateKeeperRequest extends FormRequest
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
            'fullName' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'pin' => ['nullable', 'string', 'min:4', 'max:8'],
        ];
    }
}
