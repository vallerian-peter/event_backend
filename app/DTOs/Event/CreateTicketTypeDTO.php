<?php

namespace App\DTOs\Event;

/**
 * Data Transfer Object for creating a Ticket Type within an event.
 */
class CreateTicketTypeDTO
{
    public function __construct(
        public readonly string $name,
        public readonly float $price,
        public readonly int $quantityTotal,
        public readonly string $currency = 'TZS',
        public readonly ?string $description = null,
        public readonly int $minPerOrder = 1,
        public readonly int $maxPerOrder = 10,
        public readonly ?string $salesStartAt = null,
        public readonly ?string $salesEndAt = null,
        public readonly bool $isActive = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            price: (float) ($data['price'] ?? 0),
            quantityTotal: (int) ($data['quantityTotal'] ?? 0),
            currency: (string) ($data['currency'] ?? 'TZS'),
            description: isset($data['description']) ? (string) $data['description'] : null,
            minPerOrder: (int) ($data['minPerOrder'] ?? 1),
            maxPerOrder: (int) ($data['maxPerOrder'] ?? 10),
            salesStartAt: isset($data['salesStartAt']) ? (string) $data['salesStartAt'] : null,
            salesEndAt: isset($data['salesEndAt']) ? (string) $data['salesEndAt'] : null,
            isActive: (bool) ($data['isActive'] ?? true),
        );
    }

    /**
     * Convert to array for database creation.
     *
     * @return array<string, mixed>
     */
    public function toModelAttributes(int $eventId): array
    {
        return [
            'eventId' => $eventId,
            'name' => $this->name,
            'price' => $this->price,
            'currency' => $this->currency,
            'quantityTotal' => $this->quantityTotal,
            'quantitySold' => 0,
            'description' => $this->description,
            'minPerOrder' => $this->minPerOrder,
            'maxPerOrder' => $this->maxPerOrder,
            'salesStartAt' => $this->salesStartAt,
            'salesEndAt' => $this->salesEndAt,
            'isActive' => $this->isActive,
        ];
    }
}
