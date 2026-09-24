<?php

namespace App\Services\Payments;

use App\DTOs\Payment\PaymentCheckoutDTO;

interface PaymentGatewayInterface
{
    /**
     * Initiate checkout with payment provider (AzamPay, Mobile Money, etc.).
     *
     * @return array{
     *     reference: string,
     *     transactionId: string,
     *     status: string,
     *     checkoutUrl: ?string,
     *     providerResponse: array<string, mixed>
     * }
     */
    public function initiateCheckout(PaymentCheckoutDTO $dto): array;

    /**
     * Validate and normalize inbound webhook/callback payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     transactionId: string,
     *     reference: string,
     *     amount: float,
     *     status: string,
     *     raw: array<string, mixed>
     * }
     */
    public function handleWebhook(array $payload, ?string $signature = null): array;
}
