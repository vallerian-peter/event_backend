<?php

namespace App\DTOs\Payment;

class PaymentResponseDTO
{
    /**
     * @param  array<string, mixed>  $providerData
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $transactionId,
        public readonly string $status,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $paymentMethod,
        public readonly ?string $checkoutUrl = null,
        public readonly ?string $message = null,
        public readonly array $providerData = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'transactionId' => $this->transactionId,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'paymentMethod' => $this->paymentMethod,
            'checkoutUrl' => $this->checkoutUrl,
            'message' => $this->message,
            'providerData' => $this->providerData,
        ];
    }
}
