<?php

namespace App\Http\Requests\Ticketing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketTypeRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'quantityTotal' => ['sometimes', 'required', 'integer', 'min:1'],
            'minPerOrder' => ['nullable', 'integer', 'min:1'],
            'maxPerOrder' => ['nullable', 'integer', 'min:1'],
            'salesStartAt' => ['nullable', 'date'],
            'salesEndAt' => ['nullable', 'date'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }
}
