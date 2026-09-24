<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('login')) {
            if ($this->filled('email')) {
                $this->merge(['login' => $this->input('email')]);
            } elseif ($this->filled('username')) {
                $this->merge(['login' => $this->input('username')]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Email address or username is required to request an OTP.',
        ];
    }
}
