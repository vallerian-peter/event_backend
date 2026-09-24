<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class PaymentCheckoutRequest extends FormRequest
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
            'purpose' => ['required', 'string', 'in:contribution,package_subscription,template_purchase'],
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['nullable', 'string', 'max:10'],
            'paymentMethod' => ['nullable', 'string', 'in:azampay,mpesa,tigopesa,airtel,halopesa,card,manual'],
            'mobileNumber' => ['nullable', 'string', 'max:30'],
            'eventId' => ['nullable', 'integer', 'exists:events,id'],
            'inviteeId' => ['nullable', 'integer', 'exists:invitees,id'],
            'eventOwnerId' => ['nullable', 'integer', 'exists:eventOwners,id'],
            'subscriptionId' => ['nullable', 'integer', 'exists:event_package_subscriptions,id'],
            'templateId' => ['nullable', 'integer', 'exists:templates,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
