<?php

namespace App\Http\Requests\GateKeeper;

use Illuminate\Foundation\Http\FormRequest;

class GateKeeperAuthRequest extends FormRequest
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
            'identifier' => ['required', 'string'], // phone or username
            'pin' => ['required', 'string'],
        ];
    }
}
