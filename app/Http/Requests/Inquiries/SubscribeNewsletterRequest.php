<?php

namespace App\Http\Requests\Inquiries;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeNewsletterRequest extends FormRequest
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
            'email' => ['required', 'email:rfc', 'max:191'],
            'source' => ['nullable', 'string', 'max:50'],
        ];
    }
}
