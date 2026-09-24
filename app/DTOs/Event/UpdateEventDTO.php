<?php

namespace App\DTOs\Event;

use Illuminate\Http\Request;

/**
 * Data Transfer Object for updating an existing Event.
 */
class UpdateEventDTO
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  CreateTicketTypeDTO[]|null  $ticketTypes
     */
    public function __construct(
        public readonly array $attributes,
        public readonly ?array $ticketTypes = null,
        public readonly ?string $flyerUrl = null,
    ) {}

    /**
     * Create DTO from validated Form Request or HTTP Request.
     */
    public static function fromRequest(Request $request): self
    {
        $attributes = [];

        $directKeys = [
            'title',
            'eventCategory',
            'typeOfEvent',
            'venueName',
            'address',
            'startDate',
            'endDate',
            'eventTime',
            'description',
            'contact1',
            'contact2',
            'templateId',
            'invitationTemplateTier',
            'servicePackageSlug',
            'inviteeLimit',
            'smsTimeDifference',
            'category',
            'status',
            'coverImageUrl',
            'bannerUrl',
            'flyerUrl',
        ];

        foreach ($directKeys as $key) {
            if ($request->exists($key)) {
                $attributes[$key] = $request->input($key);
            }
        }

        $booleanKeys = [
            'contributionsNeeded',
            'autoSendSms',
            'isPublic',
            'isFeatured',
        ];

        foreach ($booleanKeys as $key) {
            if ($request->exists($key)) {
                $attributes[$key] = $request->boolean($key);
            }
        }

        if ($request->exists('divisions')) {
            $attributes['divisions'] = $request->input('divisions');
        }

        if ($request->exists('divisionsPrices')) {
            $divisionsPrices = $request->input('divisionsPrices');
            if (is_string($divisionsPrices)) {
                $decoded = json_decode($divisionsPrices, true);
                $divisionsPrices = is_array($decoded) ? $decoded : null;
            }
            $attributes['divisionsPrices'] = $divisionsPrices;
        }

        if ($request->exists('serviceFeatures')) {
            $serviceFeatures = $request->input('serviceFeatures');
            if (is_string($serviceFeatures)) {
                $decoded = json_decode($serviceFeatures, true);
                $serviceFeatures = is_array($decoded) ? $decoded : [];
            }
            $attributes['serviceFeatures'] = $serviceFeatures;
        }

        $ticketTypes = null;
        if ($request->has('ticketTypes') && is_array($request->input('ticketTypes'))) {
            $ticketTypes = [];
            foreach ($request->input('ticketTypes') as $ticketData) {
                if (is_array($ticketData)) {
                    $ticketTypes[] = CreateTicketTypeDTO::fromArray($ticketData);
                }
            }
        }

        $flyerUrl = $request->filled('flyerUrl') ? (string) $request->input('flyerUrl') : null;

        return new self(
            attributes: $attributes,
            ticketTypes: $ticketTypes,
            flyerUrl: $flyerUrl,
        );
    }

    /**
     * Get array of attributes to update on model.
     *
     * @return array<string, mixed>
     */
    public function toModelAttributes(): array
    {
        $attrs = $this->attributes;
        if ($this->flyerUrl) {
            $attrs['flyerUrl'] = $this->flyerUrl;
            if (! isset($attrs['coverImageUrl'])) {
                $attrs['coverImageUrl'] = $this->flyerUrl;
            }
        }

        return $attrs;
    }
}
