<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs before validation (allows login, email, or username).
     */
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'deviceName' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'Email or username is required to sign in.',
            'password.required' => 'Password is required.',
        ];
    }
}
