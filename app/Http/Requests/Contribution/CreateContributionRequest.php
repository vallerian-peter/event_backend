<?php

namespace App\Http\Requests\Contribution;

use Illuminate\Foundation\Http\FormRequest;

class CreateContributionRequest extends FormRequest
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
            'eventId' => ['required', 'integer', 'exists:events,id'],
            'inviteeId' => ['nullable', 'integer', 'exists:invitees,id'],
            'pledgeAmount' => ['nullable', 'numeric', 'min:0'],
            'paidAmount' => ['nullable', 'numeric', 'min:0'],
            'division' => ['nullable', 'string', 'in:single,double,both'],
            'transactionId' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:pending,paid,refunded'],
        ];
    }
}
