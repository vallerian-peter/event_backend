<?php

namespace App\Services\Payments;

use App\DTOs\Payment\PaymentCheckoutDTO;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShallowAzamPayGateway implements PaymentGatewayInterface
{
    /**
     * Initiate checkout (shallow / mockable AzamPay simulation).
     */
    public function initiateCheckout(PaymentCheckoutDTO $dto): array
    {
        $reference = 'AZAM-REF-'.Str::upper(Str::random(10));
        $transactionId = 'TXN-'.Str::upper(Str::random(12));

        Log::info('ShallowAzamPayGateway: Initiated checkout', [
            'reference' => $reference,
            'transactionId' => $transactionId,
            'amount' => $dto->amount,
            'mobileNumber' => $dto->mobileNumber,
            'paymentMethod' => $dto->paymentMethod,
        ]);

        return [
            'reference' => $reference,
            'transactionId' => $transactionId,
            'status' => 'pending',
            'checkoutUrl' => "https://checkout.sandbox.azampay.co.tz/pay/{$reference}",
            'providerResponse' => [
                'provider' => 'azampay',
                'mode' => 'sandbox_shallow',
                'operator' => $dto->paymentMethod,
                'reference' => $reference,
                'transactionId' => $transactionId,
                'message' => 'Payment initiated. USSD push prompt sent to mobile device.',
            ],
        ];
    }

    /**
     * Handle incoming webhook callback from payment provider.
     */
    public function handleWebhook(array $payload, ?string $signature = null): array
    {
        $statusRaw = strtolower((string) ($payload['status'] ?? 'completed'));
        $status = in_array($statusRaw, ['success', 'completed', 'paid', 'true']) ? 'completed' : 'failed';

        $transactionId = (string) ($payload['transactionId'] ?? $payload['trans_id'] ?? Str::random(12));
        $reference = (string) ($payload['reference'] ?? $payload['reference_id'] ?? '');
        $amount = (float) ($payload['amount'] ?? 0);

        Log::info('ShallowAzamPayGateway: Webhook processed', [
            'transactionId' => $transactionId,
            'reference' => $reference,
            'status' => $status,
            'amount' => $amount,
        ]);

        return [
            'transactionId' => $transactionId,
            'reference' => $reference,
            'amount' => $amount,
            'status' => $status,
            'raw' => $payload,
        ];
    }
}
