<?php

namespace App\DTOs\Event;

use Illuminate\Http\Request;

/**
 * Data Transfer Object for creating a new Event.
 */
class CreateEventDTO
{
    /**
     * @param  array<string, mixed>|null  $divisionsPrices
     * @param  array<string, mixed>  $serviceFeatures
     * @param  CreateTicketTypeDTO[]  $ticketTypes
     */
    public function __construct(
        public readonly string $title,
        public readonly string $eventCategory = 'normal',
        public readonly ?string $typeOfEvent = null,
        public readonly ?string $venueName = null,
        public readonly ?string $address = null,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly string $eventTime = '00:00:00',
        public readonly ?string $description = null,
        public readonly ?string $contact1 = null,
        public readonly ?string $contact2 = null,
        public readonly ?int $templateId = null,
        public readonly ?string $invitationTemplateTier = null,
        public readonly bool $contributionsNeeded = true,
        public readonly ?string $divisions = null,
        public readonly ?array $divisionsPrices = null,
        public readonly string $servicePackageSlug = 'smart_reminder',
        public readonly int $inviteeLimit = 200,
        public readonly bool $autoSendSms = false,
        public readonly string $smsTimeDifference = '5days',
        public readonly array $serviceFeatures = [],
        public readonly bool $isPublic = false,
        public readonly bool $isFeatured = false,
        public readonly ?string $category = null,
        public readonly ?int $eventOwnerId = null,
        public readonly ?string $flyerUrl = null,
        public readonly ?string $coverImageUrl = null,
        public readonly ?string $bannerUrl = null,
        public readonly array $ticketTypes = [],
    ) {}

    /**
     * Create DTO from validated Form Request or HTTP Request.
     */
    public static function fromRequest(Request $request, ?int $eventOwnerId = null): self
    {
        $ticketTypes = [];
        if ($request->has('ticketTypes') && is_array($request->input('ticketTypes'))) {
            foreach ($request->input('ticketTypes') as $ticketData) {
                if (is_array($ticketData)) {
                    $ticketTypes[] = CreateTicketTypeDTO::fromArray($ticketData);
                }
            }
        }

        // Parse divisionsPrices if passed as JSON string (e.g. from multipart form-data)
        $divisionsPrices = $request->input('divisionsPrices');
        if (is_string($divisionsPrices)) {
            $decoded = json_decode($divisionsPrices, true);
            $divisionsPrices = is_array($decoded) ? $decoded : null;
        }

        // Parse serviceFeatures if passed as JSON string
        $serviceFeatures = $request->input('serviceFeatures', []);
        if (is_string($serviceFeatures)) {
            $decoded = json_decode($serviceFeatures, true);
            $serviceFeatures = is_array($decoded) ? $decoded : [];
        }

        return new self(
            title: (string) $request->input('title'),
            eventCategory: (string) $request->input('eventCategory', 'normal'),
            typeOfEvent: $request->filled('typeOfEvent') ? (string) $request->input('typeOfEvent') : null,
            venueName: $request->filled('venueName') ? (string) $request->input('venueName') : null,
            address: $request->filled('address') ? (string) $request->input('address') : null,
            startDate: $request->filled('startDate') ? (string) $request->input('startDate') : null,
            endDate: $request->filled('endDate') ? (string) $request->input('endDate') : null,
            eventTime: (string) $request->input('eventTime', '00:00:00'),
            description: $request->filled('description') ? (string) $request->input('description') : null,
            contact1: $request->filled('contact1') ? (string) $request->input('contact1') : null,
            contact2: $request->filled('contact2') ? (string) $request->input('contact2') : null,
            templateId: $request->filled('templateId') ? (int) $request->input('templateId') : null,
            invitationTemplateTier: $request->filled('invitationTemplateTier') ? (string) $request->input('invitationTemplateTier') : null,
            contributionsNeeded: $request->boolean('contributionsNeeded', true),
            divisions: $request->filled('divisions') ? (string) $request->input('divisions') : null,
            divisionsPrices: is_array($divisionsPrices) ? $divisionsPrices : null,
            servicePackageSlug: (string) $request->input('servicePackageSlug', 'smart_reminder'),
            inviteeLimit: (int) $request->input('inviteeLimit', 200),
            autoSendSms: $request->boolean('autoSendSms', false),
            smsTimeDifference: (string) $request->input('smsTimeDifference', '5days'),
            serviceFeatures: is_array($serviceFeatures) ? $serviceFeatures : [],
            isPublic: $request->boolean('isPublic', false),
            isFeatured: $request->boolean('isFeatured', false),
            category: $request->filled('category') ? (string) $request->input('category') : null,
            eventOwnerId: $eventOwnerId ?? ($request->filled('eventOwnerId') ? (int) $request->input('eventOwnerId') : null),
            flyerUrl: $request->filled('flyerUrl') ? (string) $request->input('flyerUrl') : null,
            coverImageUrl: $request->filled('coverImageUrl') ? (string) $request->input('coverImageUrl') : null,
            bannerUrl: $request->filled('bannerUrl') ? (string) $request->input('bannerUrl') : null,
            ticketTypes: $ticketTypes,
        );
    }

    /**
     * Convert DTO to associative array for Event::create().
     *
     * @return array<string, mixed>
     */
    public function toModelAttributes(): array
    {
        return [
            'title' => $this->title,
            'eventCategory' => $this->eventCategory,
            'typeOfEvent' => $this->typeOfEvent,
            'venueName' => $this->venueName,
            'address' => $this->address,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'eventTime' => $this->eventTime,
            'description' => $this->description,
            'contact1' => $this->contact1,
            'contact2' => $this->contact2,
            'templateId' => $this->templateId,
            'invitationTemplateTier' => $this->invitationTemplateTier,
            'contributionsNeeded' => $this->contributionsNeeded,
            'divisions' => $this->divisions,
            'divisionsPrices' => $this->divisionsPrices,
            'servicePackageSlug' => $this->servicePackageSlug,
            'inviteeLimit' => $this->inviteeLimit,
            'autoSendSms' => $this->autoSendSms,
            'smsTimeDifference' => $this->smsTimeDifference,
            'serviceFeatures' => $this->serviceFeatures,
            'isPublic' => $this->isPublic,
            'isFeatured' => $this->isFeatured,
            'category' => $this->category,
            'eventOwnerId' => $this->eventOwnerId,
            'flyerUrl' => $this->flyerUrl,
            'coverImageUrl' => $this->coverImageUrl ?? $this->flyerUrl,
            'bannerUrl' => $this->bannerUrl,
            'status' => 'draft',
        ];
    }
}
