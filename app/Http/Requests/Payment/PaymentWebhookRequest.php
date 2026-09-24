<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
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
            'transactionId' => ['required', 'string'],
            'reference' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric'],
            'status' => ['required', 'string'],
        ];
    }
}
