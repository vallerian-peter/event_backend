<?php

namespace App\Http\Requests\Ticketing;

use Illuminate\Foundation\Http\FormRequest;

class PublicTicketCheckoutRequest extends FormRequest
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
            'ticketTypeId' => ['required', 'integer', 'exists:ticket_types,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:30'],
            'paymentMethod' => ['nullable', 'string', 'max:50'],
            'attendees' => ['nullable', 'array'],
            'attendees.*.name' => ['nullable', 'string', 'max:255'],
            'attendees.*.email' => ['nullable', 'email', 'max:255'],
            'attendees.*.phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
