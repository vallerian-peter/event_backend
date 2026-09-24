<?php

namespace App\Services;

use App\DTOs\Payment\PaymentCheckoutDTO;
use App\DTOs\Payment\PaymentResponseDTO;
use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\Event;
use App\Models\EventFeatureBalance;
use App\Models\EventPackageSubscription;
use App\Models\EventsPayment;
use App\Models\EventsTemplatePayment;
use App\Models\User;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\ShallowAzamPayGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected PaymentGatewayInterface $gateway;

    protected ContributionService $contributionService;

    public function __construct(?PaymentGatewayInterface $gateway = null, ?ContributionService $contributionService = null)
    {
        $this->gateway = $gateway ?? new ShallowAzamPayGateway();
        $this->contributionService = $contributionService ?? new ContributionService();
    }

    /**
     * Initiate checkout with payment gateway and record pending payment.
     */
    public function checkout(PaymentCheckoutDTO $dto, ?User $actor = null): PaymentResponseDTO
    {
        $gatewayResult = $this->gateway->initiateCheckout($dto);

        return DB::transaction(function () use ($dto, $gatewayResult, $actor) {
            $eventOwnerId = $dto->eventOwnerId;
            if (! $eventOwnerId && $actor && $actor->roleId) {
                $eventOwnerId = $actor->roleId;
            }

            if (! $eventOwnerId && $dto->eventId) {
                $event = Event::find($dto->eventId);
                $eventOwnerId = $event?->eventOwnerId ?? 1;
            }

            /** @var EventsPayment $payment */
            $payment = EventsPayment::create([
                'eventOwnerId' => $eventOwnerId ?? 1,
                'systemUserId' => ($actor && $actor->role === \App\Enums\UserRole::Admin) ? $actor->roleId : null,
                'eventId' => $dto->eventId,
                'eventData' => array_merge($dto->metadata, [
                    'purpose' => $dto->purpose,
                    'inviteeId' => $dto->inviteeId,
                    'subscriptionId' => $dto->subscriptionId,
                    'templateId' => $dto->templateId,
                    'reference' => $gatewayResult['reference'],
                ]),
                'amount' => (string) $dto->amount,
                'paidAmount' => '0',
                'currency' => $dto->currency,
                'paymentMethod' => $dto->paymentMethod,
                'mobileNumber' => $dto->mobileNumber,
                'transactionId' => $gatewayResult['transactionId'],
                'status' => ContributionStatus::Pending,
            ]);

            // If contribution checkout, create or track contribution
            if ($dto->purpose === 'contribution' && $dto->eventId) {
                Contribution::create([
                    'eventId' => $dto->eventId,
                    'inviteeId' => $dto->inviteeId,
                    'pledgeAmount' => (string) $dto->amount,
                    'paidAmount' => '0',
                    'transactionId' => $gatewayResult['transactionId'],
                    'status' => ContributionStatus::Pending,
                ]);
            }

            return new PaymentResponseDTO(
                reference: $gatewayResult['reference'],
                transactionId: $gatewayResult['transactionId'],
                status: $gatewayResult['status'],
                amount: $dto->amount,
                currency: $dto->currency,
                paymentMethod: $dto->paymentMethod,
                checkoutUrl: $gatewayResult['checkoutUrl'],
                message: 'Checkout initiated successfully.',
                providerData: $gatewayResult['providerResponse'],
            );
        });
    }

    /**
     * Handle incoming payment gateway webhook / callback.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleWebhook(array $payload, ?string $signature = null): array
    {
        $normalized = $this->gateway->handleWebhook($payload, $signature);

        return DB::transaction(function () use ($normalized) {
            $transactionId = $normalized['transactionId'];
            $status = $normalized['status'];
            $amount = $normalized['amount'];

            /** @var EventsPayment|null $payment */
            $payment = EventsPayment::where('transactionId', $transactionId)->first();

            if (! $payment && ! empty($normalized['reference'])) {
                $payment = EventsPayment::where('eventData->reference', $normalized['reference'])->first();
            }

            $isCompleted = ($status === 'completed');

            if ($payment) {
                $payment->status = $isCompleted ? ContributionStatus::Paid : ContributionStatus::Pending;
                if ($isCompleted) {
                    $payment->paidAmount = (string) ($amount > 0 ? $amount : $payment->amount);
                }
                $payment->save();

                // Fulfill based on purpose
                $eventData = $payment->eventData ?? [];
                $purpose = $eventData['purpose'] ?? 'contribution';

                if ($purpose === 'contribution' && $isCompleted) {
                    $this->fulfillContributionPayment($payment);
                } elseif ($purpose === 'package_subscription' && $isCompleted) {
                    $this->fulfillPackageSubscription($payment);
                } elseif ($purpose === 'template_purchase' && $isCompleted) {
                    $this->fulfillTemplatePayment($payment);
                }
            } else {
                Log::warning('PaymentService: Webhook received for unknown transaction', ['transactionId' => $transactionId]);
            }

            return [
                'status' => 'success',
                'transactionId' => $transactionId,
                'paymentStatus' => $isCompleted ? 'paid' : 'failed',
                'matchedPaymentId' => $payment?->id,
            ];
        });
    }

    /**
     * Get payment status by transaction ID or reference.
     */
    public function getPaymentStatus(string $identifier): ?EventsPayment
    {
        return EventsPayment::where('transactionId', $identifier)
            ->orWhere('id', is_numeric($identifier) ? (int) $identifier : 0)
            ->first();
    }

    /**
     * Fulfill contribution and synchronize attendee status.
     */
    protected function fulfillContributionPayment(EventsPayment $payment): void
    {
        $contribution = Contribution::where('transactionId', $payment->transactionId)->first();

        if ($contribution) {
            $contribution->paidAmount = (string) $payment->paidAmount;
            $contribution->status = ContributionStatus::Paid;
            $contribution->save();

            if ($contribution->inviteeId) {
                $this->contributionService->syncInviteeContributionStatus($contribution->inviteeId);
            }
        }
    }

    /**
     * Fulfill package subscription and allocate feature balance.
     */
    protected function fulfillPackageSubscription(EventsPayment $payment): void
    {
        $eventData = $payment->eventData ?? [];
        $subscriptionId = $eventData['subscriptionId'] ?? null;

        if ($subscriptionId) {
            $subscription = EventPackageSubscription::find($subscriptionId);
            if ($subscription) {
                $subscription->status = 'active';
                $subscription->save();

                // Allocate feature balance if event is attached
                if ($payment->eventId) {
                    EventFeatureBalance::firstOrCreate(
                        [
                            'eventId' => $payment->eventId,
                            'featureKey' => 'smsUnits',
                        ],
                        [
                            'subscriptionId' => $subscription->id,
                            'grantedUnits' => 500,
                            'reservedUnits' => 0,
                            'consumedUnits' => 0,
                        ]
                    );

                    EventFeatureBalance::firstOrCreate(
                        [
                            'eventId' => $payment->eventId,
                            'featureKey' => 'whatsappUnits',
                        ],
                        [
                            'subscriptionId' => $subscription->id,
                            'grantedUnits' => 200,
                            'reservedUnits' => 0,
                            'consumedUnits' => 0,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Fulfill template purchase.
     */
    protected function fulfillTemplatePayment(EventsPayment $payment): void
    {
        $eventData = $payment->eventData ?? [];
        $templateId = $eventData['templateId'] ?? null;

        if ($templateId && $payment->eventId) {
            EventsTemplatePayment::firstOrCreate([
                'eventId' => $payment->eventId,
                'templateId' => $templateId,
            ], [
                'paymentId' => $payment->id,
            ]);
        }
    }
}
