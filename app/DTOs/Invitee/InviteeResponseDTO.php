<?php

namespace App\DTOs\Invitee;

use App\Enums\EventDivision;
use App\Models\Invitee;
use Illuminate\Support\Facades\Storage;

/**
 * Data Transfer Object for formatted Invitee JSON responses matching event-management-system.
 */
class InviteeResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $eventId,
        public readonly ?string $eventTitle,
        public readonly string $fullName,
        public readonly string $title,
        public readonly ?string $email,
        public readonly ?string $normalPhone,
        public readonly ?string $whatsappPhone,
        public readonly string $status,
        public readonly string $smsStatus,
        public readonly ?string $division,
        public readonly string $contributionStatus,
        public readonly ?float $pledgeAmount,
        public readonly ?float $paidAmount,
        public readonly ?string $entryPassCode,
        public readonly ?string $entryPassQrImageUrl,
        public readonly ?string $entryPassConsumedAt,
        public readonly ?string $entryPassScannedByUuid,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public static function fromModel(Invitee $invitee, ?string $qrImageUrl = null): self
    {
        // 1. Get latest contribution details
        $latestContribution = $invitee->relationLoaded('contributions')
            ? $invitee->contributions->sortByDesc('updatedAt')->first()
            : $invitee->contributions()->latest('updatedAt')->first();

        $pledge = $latestContribution ? (float) $latestContribution->pledgeAmount : null;
        $paid = $latestContribution ? (float) $latestContribution->paidAmount : null;

        $division = null;
        if ($invitee->division instanceof EventDivision) {
            $division = $invitee->division->value;
        } elseif (is_string($invitee->division) && $invitee->division !== '') {
            $division = $invitee->division;
        } elseif ($latestContribution?->division instanceof EventDivision) {
            $division = $latestContribution->division->value;
        } elseif (is_string($latestContribution?->division) && $latestContribution->division !== '') {
            $division = $latestContribution->division;
        }

        // 2. Resolve QR image URL
        if (! $qrImageUrl) {
            $qrPath = "events/{$invitee->eventId}/entry-passes/{$invitee->id}.png";
            $qrImageUrl = Storage::disk('r2')->url($qrPath);
        }

        $eventTitle = null;
        if ($invitee->relationLoaded('event') && $invitee->event) {
            $eventTitle = $invitee->event->title;
        }

        return new self(
            id: $invitee->id,
            eventId: $invitee->eventId,
            eventTitle: $eventTitle,
            fullName: $invitee->fullName,
            title: $invitee->title ?? 'Normal Person',
            email: $invitee->email,
            normalPhone: $invitee->normalPhone,
            whatsappPhone: $invitee->whatsappPhone,
            status: $invitee->status->value ?? (string) $invitee->status,
            smsStatus: $invitee->smsStatus->value ?? (string) $invitee->smsStatus,
            division: $division,
            contributionStatus: $invitee->contributionStatus->value ?? (string) $invitee->contributionStatus,
            pledgeAmount: $pledge,
            paidAmount: $paid,
            entryPassCode: $invitee->entryPassCode,
            entryPassQrImageUrl: $qrImageUrl,
            entryPassConsumedAt: $invitee->entryPassConsumedAt?->toIso8601String(),
            entryPassScannedByUuid: $invitee->entryPassScannedByUuid,
            createdAt: $invitee->createdAt?->toIso8601String(),
            updatedAt: $invitee->updatedAt?->toIso8601String(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'eventId' => $this->eventId,
            'eventTitle' => $this->eventTitle,
            'fullName' => $this->fullName,
            'title' => $this->title,
            'email' => $this->email,
            'normalPhone' => $this->normalPhone,
            'whatsappPhone' => $this->whatsappPhone,
            'status' => $this->status,
            'smsStatus' => $this->smsStatus,
            'division' => $this->division,
            'contributionStatus' => $this->contributionStatus,
            'pledgeAmount' => $this->pledgeAmount,
            'paidAmount' => $this->paidAmount,
            'entryPassCode' => $this->entryPassCode,
            'entryPassQrImageUrl' => $this->entryPassQrImageUrl,
            'entryPassConsumedAt' => $this->entryPassConsumedAt,
            'entryPassScannedByUuid' => $this->entryPassScannedByUuid,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
