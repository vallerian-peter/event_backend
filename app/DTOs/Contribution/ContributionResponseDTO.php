<?php

namespace App\DTOs\Contribution;

use App\Enums\EventDivision;
use App\Models\Contribution;

class ContributionResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $inviteeId,
        public readonly ?string $inviteeName,
        public readonly ?int $eventId,
        public readonly ?string $eventTitle,
        public readonly float $pledgeAmount,
        public readonly float $paidAmount,
        public readonly ?string $division,
        public readonly string $status,
        public readonly ?string $transactionId,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public static function fromModel(Contribution $contribution): self
    {
        $division = null;
        if ($contribution->division instanceof EventDivision) {
            $division = $contribution->division->value;
        } elseif (is_string($contribution->division)) {
            $division = $contribution->division;
        }

        $inviteeName = null;
        if ($contribution->relationLoaded('invitee') && $contribution->invitee) {
            $inviteeName = $contribution->invitee->fullName;
        }

        $eventTitle = null;
        if ($contribution->relationLoaded('event') && $contribution->event) {
            $eventTitle = $contribution->event->title;
        }

        return new self(
            id: $contribution->id,
            inviteeId: $contribution->inviteeId,
            inviteeName: $inviteeName,
            eventId: $contribution->eventId,
            eventTitle: $eventTitle,
            pledgeAmount: (float) $contribution->pledgeAmount,
            paidAmount: (float) $contribution->paidAmount,
            division: $division,
            status: $contribution->status->value ?? (string) $contribution->status,
            transactionId: $contribution->transactionId,
            createdAt: $contribution->createdAt?->toISOString(),
            updatedAt: $contribution->updatedAt?->toISOString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'inviteeId' => $this->inviteeId,
            'inviteeName' => $this->inviteeName,
            'eventId' => $this->eventId,
            'eventTitle' => $this->eventTitle,
            'pledgeAmount' => $this->pledgeAmount,
            'paidAmount' => $this->paidAmount,
            'division' => $this->division,
            'status' => $this->status,
            'transactionId' => $this->transactionId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
