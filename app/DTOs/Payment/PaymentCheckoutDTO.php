<?php

namespace App\DTOs\Payment;

use App\Http\Requests\Payment\PaymentCheckoutRequest;

class PaymentCheckoutDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $purpose,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $paymentMethod,
        public readonly ?string $mobileNumber = null,
        public readonly ?int $eventId = null,
        public readonly ?int $inviteeId = null,
        public readonly ?int $eventOwnerId = null,
        public readonly ?int $subscriptionId = null,
        public readonly ?int $templateId = null,
        public readonly array $metadata = [],
    ) {}

    public static function fromRequest(PaymentCheckoutRequest $request): self
    {
        return new self(
            purpose: $request->input('purpose', 'contribution'),
            amount: (float) $request->input('amount'),
            currency: strtoupper($request->input('currency', 'TZS')),
            paymentMethod: $request->input('paymentMethod', 'azampay'),
            mobileNumber: $request->input('mobileNumber'),
            eventId: $request->has('eventId') ? (int) $request->input('eventId') : null,
            inviteeId: $request->has('inviteeId') ? (int) $request->input('inviteeId') : null,
            eventOwnerId: $request->has('eventOwnerId') ? (int) $request->input('eventOwnerId') : null,
            subscriptionId: $request->has('subscriptionId') ? (int) $request->input('subscriptionId') : null,
            templateId: $request->has('templateId') ? (int) $request->input('templateId') : null,
            metadata: $request->input('metadata', []),
        );
    }
}
