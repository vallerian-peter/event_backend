<?php

namespace App\Http\Requests\Ticketing;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'quantityTotal' => ['required', 'integer', 'min:1'],
            'minPerOrder' => ['nullable', 'integer', 'min:1'],
            'maxPerOrder' => ['nullable', 'integer', 'min:1', 'gte:minPerOrder'],
            'salesStartAt' => ['nullable', 'date'],
            'salesEndAt' => ['nullable', 'date', 'after_or_equal:salesStartAt'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }
}
