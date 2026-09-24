<?php

namespace App\Http\Requests\Inquiries;

use Illuminate\Foundation\Http\FormRequest;

class CreateProjectBriefRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:150'],
            'fullName' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'phone' => ['required', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:80'],
            'eventType' => ['nullable', 'string', 'max:80'],
            'date' => ['nullable', 'date'],
            'eventDate' => ['nullable', 'date'],
            'eventTime' => ['nullable', 'string', 'max:20'],
            'venueLocation' => ['nullable', 'string', 'max:150'],
            'guestCount' => ['nullable', 'string', 'max:50'],
            'estimatedGuests' => ['nullable', 'string', 'max:50'],
            'budgetRange' => ['nullable', 'string', 'max:50'],
            'requestedServices' => ['nullable', 'array'],
            'requestedServices.*' => ['string', 'max:80'],
            'message' => ['required', 'string', 'max:3000'],
        ];
    }

    /**
     * Ensure at least one name representation is provided.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->input('name')) && empty($this->input('fullName'))) {
                $validator->errors()->add('name', 'The full name field is required.');
            }
        });
    }

    /**
     * Normalize payload into standardized keys.
     *
     * @return array<string, mixed>
     */
    public function normalizedData(): array
    {
        $fullName = (string) ($this->input('fullName') ?: $this->input('name'));
        $eventType = (string) ($this->input('eventType') ?: $this->input('type') ?: 'General Event');
        $eventDate = $this->input('eventDate') ?: $this->input('date');
        $estimatedGuests = $this->input('estimatedGuests') ?: $this->input('guestCount');

        return [
            'fullName' => trim($fullName),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => trim((string) $this->input('phone')),
            'eventType' => trim($eventType),
            'eventDate' => $eventDate,
            'eventTime' => $this->input('eventTime'),
            'venueLocation' => $this->input('venueLocation'),
            'estimatedGuests' => $estimatedGuests,
            'budgetRange' => $this->input('budgetRange'),
            'requestedServices' => $this->input('requestedServices'),
            'message' => trim((string) $this->input('message')),
        ];
    }
}
