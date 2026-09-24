<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Payment\PaymentCheckoutDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\PaymentCheckoutRequest;
use App\Http\Requests\Payment\PaymentWebhookRequest;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Initiate payment checkout (AzamPay, Mobile Money, etc.).
     *
     * POST /api/v1/payments/checkout
     */
    public function checkout(PaymentCheckoutRequest $request): JsonResponse
    {
        $dto = PaymentCheckoutDTO::fromRequest($request);
        $responseDto = $this->paymentService->checkout($dto, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => $responseDto->message,
            'data' => $responseDto->toArray(),
        ], 201);
    }

    /**
     * Inbound webhook / callback handler for payment providers.
     *
     * POST /api/v1/payments/webhook
     * POST /api/v1/payments/callback
     */
    public function webhook(PaymentWebhookRequest $request): JsonResponse
    {
        $signature = $request->header('X-Azampay-Signature') ?? $request->header('X-Signature');
        $result = $this->paymentService->handleWebhook($request->validated(), $signature);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received and processed.',
            'data' => $result,
        ]);
    }

    /**
     * Check payment status by transaction ID or reference.
     *
     * GET /api/v1/payments/{identifier}/status
     */
    public function status(Request $request, string $identifier): JsonResponse
    {
        $payment = $this->paymentService->getPaymentStatus($identifier);

        if (! $payment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment transaction not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'transactionId' => $payment->transactionId,
                'status' => $payment->status->value ?? (string) $payment->status,
                'amount' => (float) $payment->amount,
                'paidAmount' => (float) $payment->paidAmount,
                'currency' => $payment->currency,
                'paymentMethod' => $payment->paymentMethod,
                'createdAt' => $payment->createdAt?->toISOString(),
            ],
        ]);
    }
}
