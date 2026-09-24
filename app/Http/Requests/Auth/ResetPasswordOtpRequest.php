<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Support email or username
        if (! $this->filled('login')) {
            if ($this->filled('email')) {
                $this->merge(['login' => $this->input('email')]);
            } elseif ($this->filled('username')) {
                $this->merge(['login' => $this->input('username')]);
            }
        }

        // Support password or newPassword
        if (! $this->filled('newPassword') && $this->filled('password')) {
            $this->merge(['newPassword' => $this->input('password')]);
        } elseif (! $this->filled('password') && $this->filled('newPassword')) {
            $this->merge(['password' => $this->input('newPassword')]);
        }
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:6', 'regex:/[A-Z]/'],
            'otp' => ['nullable', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Email address or username is required.',
            'newPassword.required' => 'New password is required.',
            'newPassword.min' => 'Password must be at least 6 characters long.',
            'newPassword.regex' => 'Password must contain at least one uppercase (capital) letter.',
        ];
    }
}
