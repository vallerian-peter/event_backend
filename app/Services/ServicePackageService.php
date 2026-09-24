<?php

namespace App\Services;

use App\Enums\EventLanguage;
use App\Enums\PurchaseType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventFeatureBalance;
use App\Models\EventOwner;
use App\Models\EventOwnerPreference;
use App\Models\EventPackageSubscription;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicePackageService
{
    /**
     * List all available service packages.
     *
     * @return Collection<int, ServicePackage>
     */
    public function listPackages(): Collection
    {
        return ServicePackage::where('isActive', true)
            ->orderBy('priceUsd', 'asc')
            ->get();
    }

    /**
     * Get a specific service package by id or slug.
     */
    public function getPackage(int|string $idOrSlug): ServicePackage
    {
        if (is_numeric($idOrSlug)) {
            return ServicePackage::findOrFail((int) $idOrSlug);
        }

        return ServicePackage::where('slug', $idOrSlug)->firstOrFail();
    }

    /**
     * Subscribe an event to a service package.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function subscribeEvent(int $eventId, int $packageId, array $options = [], ?User $actor = null): array
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeOwnerOrAdmin($event, $actor);

        $package = ServicePackage::findOrFail($packageId);

        return DB::transaction(function () use ($event, $package, $options) {
            $inviteeLimit = $options['inviteeLimit'] ?? $package->limits['maxInvitees'] ?? 500;
            $smsUnits = $options['smsUnits'] ?? $package->limits['includedSms'] ?? 100;

            /** @var EventPackageSubscription $subscription */
            $subscription = EventPackageSubscription::create([
                'eventId' => $event->id,
                'servicePackageId' => $package->id,
                'paymentId' => $options['paymentId'] ?? null,
                'purchaseType' => PurchaseType::Initial,
                'status' => SubscriptionStatus::Active,
                'packageSnapshot' => [
                    'slug' => $package->slug,
                    'name' => $package->name,
                    'priceUsd' => $package->priceUsd,
                    'capabilities' => $package->capabilities,
                    'limits' => $package->limits,
                ],
                'recordedAmountTzs' => (float) ($options['amountTzs'] ?? ($package->priceUsd * 2600)),
                'inviteeLimit' => (int) $inviteeLimit,
                'activatedAt' => now(),
                'expiresAt' => now()->addMonths(6),
            ]);

            // Update or create SMS balance for the event
            $balance = EventFeatureBalance::updateOrCreate(
                [
                    'eventId' => $event->id,
                    'featureKey' => 'sms_broadcast',
                ],
                [
                    'subscriptionId' => $subscription->id,
                    'grantedUnits' => $smsUnits,
                    'reservedUnits' => 0,
                    'consumedUnits' => 0,
                    'metadata' => ['packageSlug' => $package->slug],
                ]
            );

            // Update event record metadata
            $event->update([
                'servicePackageSlug' => $package->slug,
                'serviceFeatures' => $package->capabilities ?? [],
                'inviteeLimit' => (int) $inviteeLimit,
            ]);

            return [
                'event' => $event->fresh(),
                'subscription' => $subscription,
                'featureBalance' => $balance,
            ];
        });
    }

    /**
     * Get active subscription and feature balance for an event.
     *
     * @return array<string, mixed>
     */
    public function getEventSubscription(int $eventId, ?User $actor = null): array
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeOwnerOrAdmin($event, $actor);

        $subscription = EventPackageSubscription::with('servicePackage')
            ->where('eventId', $eventId)
            ->where('status', SubscriptionStatus::Active)
            ->latest('createdAt')
            ->first();

        $balances = EventFeatureBalance::where('eventId', $eventId)->get();

        return [
            'eventId' => $event->id,
            'servicePackageSlug' => $event->servicePackageSlug,
            'inviteeLimit' => $event->inviteeLimit,
            'subscription' => $subscription,
            'balances' => $balances,
        ];
    }

    /**
     * Get or create event owner preferences.
     */
    public function getOwnerPreferences(User $actor): EventOwnerPreference
    {
        $ownerId = $this->resolveOwnerId($actor);

        /** @var EventOwnerPreference $preference */
        $preference = EventOwnerPreference::firstOrCreate(
            ['eventOwnerId' => $ownerId],
            [
                'eventLanguage' => EventLanguage::Swahili,
                'timezone' => 'Africa/Dar_es_Salaam',
                'preferredChannels' => ['sms', 'whatsapp'],
                'committeeSettings' => ['allowMemberInvite' => true, 'notifyOnMeeting' => true],
                'messagePreferences' => ['includeEventLink' => true],
            ]
        );

        return $preference;
    }

    /**
     * Update event owner preferences.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateOwnerPreferences(User $actor, array $data): EventOwnerPreference
    {
        $ownerId = $this->resolveOwnerId($actor);

        /** @var EventOwnerPreference $preference */
        $preference = EventOwnerPreference::firstOrCreate(['eventOwnerId' => $ownerId]);

        $preference->update($data);

        return $preference->fresh();
    }

    /**
     * Resolve owner ID for user.
     *
     * @throws ValidationException
     */
    protected function resolveOwnerId(User $actor): int
    {
        if ($actor->role === UserRole::EventOwner && $actor->roleId) {
            return (int) $actor->roleId;
        }

        /** @var EventOwner|null $owner */
        $owner = EventOwner::where('userId', $actor->id)->first();
        if ($owner) {
            return $owner->id;
        }

        // Create an event owner record if none exists for this user
        $owner = EventOwner::create([
            'userId' => $actor->id,
            'fullName' => $actor->name,
            'phone' => $actor->phone,
            'email' => $actor->email,
        ]);

        return $owner->id;
    }

    /**
     * Authorize event owner or admin.
     *
     * @throws AuthorizationException
     */
    protected function authorizeOwnerOrAdmin(Event $event, ?User $actor): void
    {
        if (! $actor) {
            return;
        }

        if ($actor->role === UserRole::Admin) {
            return;
        }

        if ($actor->role === UserRole::EventOwner && (int) $actor->roleId === (int) $event->eventOwnerId) {
            return;
        }

        throw new AuthorizationException('You are not authorized to manage subscriptions for this event.');
    }
}
