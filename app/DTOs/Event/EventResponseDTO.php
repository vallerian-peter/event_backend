<?php

namespace App\DTOs\Event;

use App\Models\Event;

/**
 * Data Transfer Object for formatted Event JSON response with strict camelCase keys.
 */
class EventResponseDTO
{
    /**
     * @param  array<string, mixed>|null  $divisionsPrices
     * @param  array<string, mixed>  $serviceFeatures
     * @param  array<string, mixed>|null  $owner
     * @param  array<string, mixed>|null  $template
     * @param  array<int, array<string, mixed>>  $ticketTypes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $eventCategory,
        public readonly string $status,
        public readonly ?string $typeOfEvent,
        public readonly ?string $venueName,
        public readonly ?string $address,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
        public readonly string $eventTime,
        public readonly ?string $description,
        public readonly ?string $contact1,
        public readonly ?string $contact2,
        public readonly ?string $coverImageUrl,
        public readonly ?string $bannerUrl,
        public readonly ?string $flyerUrl,
        public readonly ?int $templateId,
        public readonly ?string $invitationTemplateTier,
        public readonly bool $contributionsNeeded,
        public readonly ?string $divisions,
        public readonly ?array $divisionsPrices,
        public readonly string $servicePackageSlug,
        public readonly array $serviceFeatures,
        public readonly int $inviteeLimit,
        public readonly bool $autoSendSms,
        public readonly string $smsTimeDifference,
        public readonly bool $isPublic,
        public readonly bool $isFeatured,
        public readonly ?string $category,
        public readonly ?int $eventOwnerId,
        public readonly ?array $owner = null,
        public readonly ?array $template = null,
        public readonly array $ticketTypes = [],
        public readonly int $inviteesCount = 0,
        public readonly int $ticketOrdersCount = 0,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    /**
     * Build EventResponseDTO from Eloquent Event model.
     */
    public static function fromModel(Event $event): self
    {
        $ownerData = null;
        if ($event->relationLoaded('owner') && $event->owner) {
            $owner = $event->owner;
            $ownerData = [
                'id' => $owner->id,
                'firstName' => $owner->firstName,
                'middleName' => $owner->middleName,
                'lastName' => $owner->lastName,
                'email' => $owner->email,
                'phone' => $owner->phone,
            ];
        }

        $templateData = null;
        if ($event->relationLoaded('template') && $event->template) {
            $t = $event->template;
            $templateData = [
                'id' => $t->id,
                'name' => $t->name,
                'pathUrl' => $t->pathUrl,
                'previewImageUrl' => $t->previewImageUrl,
                'isPremium' => (bool) $t->isPremium,
                'price' => (float) $t->price,
                'category' => $t->category,
            ];
        }

        $ticketTypesList = [];
        if ($event->relationLoaded('ticketTypes') && $event->ticketTypes) {
            foreach ($event->ticketTypes as $ticketType) {
                $ticketTypesList[] = [
                    'id' => $ticketType->id,
                    'eventId' => $ticketType->eventId,
                    'name' => $ticketType->name,
                    'description' => $ticketType->description,
                    'price' => (float) $ticketType->price,
                    'currency' => $ticketType->currency,
                    'quantityTotal' => $ticketType->quantityTotal,
                    'quantitySold' => $ticketType->quantitySold,
                    'availableQuantity' => $ticketType->availableQuantity(),
                    'minPerOrder' => $ticketType->minPerOrder,
                    'maxPerOrder' => $ticketType->maxPerOrder,
                    'salesStartAt' => $ticketType->salesStartAt ? $ticketType->salesStartAt->toISOString() : null,
                    'salesEndAt' => $ticketType->salesEndAt ? $ticketType->salesEndAt->toISOString() : null,
                    'isActive' => $ticketType->isActive,
                ];
            }
        }

        return new self(
            id: $event->id,
            title: $event->title,
            slug: $event->slug,
            eventCategory: is_string($event->eventCategory) ? $event->eventCategory : $event->eventCategory->value,
            status: is_string($event->status) ? $event->status : $event->status->value,
            typeOfEvent: is_string($event->typeOfEvent) ? $event->typeOfEvent : $event->typeOfEvent?->value,
            venueName: $event->venueName,
            address: $event->address,
            startDate: $event->startDate ? (is_string($event->startDate) ? $event->startDate : $event->startDate->format('Y-m-d')) : null,
            endDate: $event->endDate ? (is_string($event->endDate) ? $event->endDate : $event->endDate->format('Y-m-d')) : null,
            eventTime: (string) $event->eventTime,
            description: $event->description,
            contact1: $event->contact1,
            contact2: $event->contact2,
            coverImageUrl: $event->coverImageUrl,
            bannerUrl: $event->bannerUrl,
            flyerUrl: $event->flyerUrl,
            templateId: $event->templateId,
            invitationTemplateTier: is_string($event->invitationTemplateTier) ? $event->invitationTemplateTier : $event->invitationTemplateTier?->value,
            contributionsNeeded: (bool) $event->contributionsNeeded,
            divisions: is_string($event->divisions) ? $event->divisions : $event->divisions?->value,
            divisionsPrices: $event->divisionsPrices,
            servicePackageSlug: $event->servicePackageSlug,
            serviceFeatures: $event->serviceFeatures ?? [],
            inviteeLimit: (int) $event->inviteeLimit,
            autoSendSms: (bool) $event->autoSendSms,
            smsTimeDifference: (string) $event->smsTimeDifference,
            isPublic: (bool) $event->isPublic,
            isFeatured: (bool) $event->isFeatured,
            category: $event->category,
            eventOwnerId: $event->eventOwnerId,
            owner: $ownerData,
            template: $templateData,
            ticketTypes: $ticketTypesList,
            inviteesCount: (int) ($event->invitees_count ?? 0),
            ticketOrdersCount: (int) ($event->ticket_orders_count ?? 0),
            createdAt: $event->createdAt ? $event->createdAt->toISOString() : null,
            updatedAt: $event->updatedAt ? $event->updatedAt->toISOString() : null,
        );
    }

    /**
     * Convert DTO to JSON-serializable array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'eventCategory' => $this->eventCategory,
            'status' => $this->status,
            'typeOfEvent' => $this->typeOfEvent,
            'venueName' => $this->venueName,
            'address' => $this->address,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'eventTime' => $this->eventTime,
            'description' => $this->description,
            'contact1' => $this->contact1,
            'contact2' => $this->contact2,
            'coverImageUrl' => $this->coverImageUrl,
            'bannerUrl' => $this->bannerUrl,
            'flyerUrl' => $this->flyerUrl,
            'templateId' => $this->templateId,
            'invitationTemplateTier' => $this->invitationTemplateTier,
            'contributionsNeeded' => $this->contributionsNeeded,
            'divisions' => $this->divisions,
            'divisionsPrices' => $this->divisionsPrices,
            'servicePackageSlug' => $this->servicePackageSlug,
            'serviceFeatures' => $this->serviceFeatures,
            'inviteeLimit' => $this->inviteeLimit,
            'autoSendSms' => $this->autoSendSms,
            'smsTimeDifference' => $this->smsTimeDifference,
            'isPublic' => $this->isPublic,
            'isFeatured' => $this->isFeatured,
            'category' => $this->category,
            'eventOwnerId' => $this->eventOwnerId,
            'owner' => $this->owner,
            'template' => $this->template,
            'ticketTypes' => $this->ticketTypes,
            'inviteesCount' => $this->inviteesCount,
            'ticketOrdersCount' => $this->ticketOrdersCount,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
