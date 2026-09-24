<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // User type / role
            'userType' => ['nullable', 'string', 'in:event_owner,gate_keeper,admin,eventOwner,gateKeeper'],
            'role' => ['nullable', 'string', 'in:event_owner,gate_keeper,admin,eventOwner,gateKeeper'],

            // Core credentials
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'regex:/[A-Z]/'],
            'username' => ['nullable', 'string', 'min:3', 'max:60', 'alpha_dash', 'unique:users,username'],
            'phone' => ['nullable', 'string', 'max:30'],

            // Names (flexible: can provide name/fullName or firstName/lastName)
            'name' => ['nullable', 'string', 'max:255'],
            'fullName' => ['nullable', 'string', 'max:255'],
            'firstName' => ['nullable', 'string', 'max:120'],
            'middleName' => ['nullable', 'string', 'max:120'],
            'lastName' => ['nullable', 'string', 'max:120'],

            // Metadata
            'avatarUrl' => ['nullable', 'string', 'url', 'max:1000'],
            'deviceName' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'An account with this email already exists.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters long.',
            'password.regex' => 'Password must contain at least one uppercase (capital) letter.',
            'username.unique' => 'This username is already taken.',
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes, and underscores.',
            'userType.in' => 'User type must be one of: event_owner, gate_keeper, or admin.',
        ];
    }
}
