<?php

namespace App\DTOs\Invitee;

use App\Enums\EventDivision;
use App\Enums\InviteeStatus;
use Illuminate\Http\Request;

/**
 * Data Transfer Object for creating a new Invitee.
 */
class CreateInviteeDTO
{
    public function __construct(
        public readonly int $eventId,
        public readonly string $fullName,
        public readonly string $title = 'Normal Person',
        public readonly ?string $email = null,
        public readonly ?string $normalPhone = null,
        public readonly ?string $whatsappPhone = null,
        public readonly string $status = 'active',
        public readonly ?string $division = null,
        public readonly float $pledgeAmount = 0.0,
        public readonly float $paidAmount = 0.0,
    ) {}

    /**
     * Create DTO from validated Form Request or HTTP Request.
     */
    public static function fromRequest(Request $request): self
    {
        $status = $request->input('status', $request->input('inviteStatus', 'active'));
        if ($status instanceof InviteeStatus) {
            $status = $status->value;
        }

        $division = $request->input('division');
        if ($division instanceof EventDivision) {
            $division = $division->value;
        }

        return new self(
            eventId: (int) $request->input('eventId'),
            fullName: trim((string) $request->input('fullName')),
            title: trim((string) $request->input('title', 'Normal Person')),
            email: $request->filled('email') ? trim((string) $request->input('email')) : null,
            normalPhone: $request->filled('normalPhone') ? trim((string) $request->input('normalPhone')) : null,
            whatsappPhone: $request->filled('whatsappPhone') ? trim((string) $request->input('whatsappPhone')) : null,
            status: (string) ($status ?: 'active'),
            division: $division ? (string) $division : null,
            pledgeAmount: (float) $request->input('pledgeAmount', 0.0),
            paidAmount: (float) $request->input('paidAmount', 0.0),
        );
    }

    /**
     * Create DTO from raw associative array (for bulk imports).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?int $defaultEventId = null): self
    {
        $status = $data['status'] ?? $data['inviteStatus'] ?? 'active';
        if ($status instanceof InviteeStatus) {
            $status = $status->value;
        }

        $division = $data['division'] ?? null;
        if ($division instanceof EventDivision) {
            $division = $division->value;
        }

        return new self(
            eventId: (int) ($data['eventId'] ?? $defaultEventId ?? 0),
            fullName: trim((string) ($data['fullName'] ?? '')),
            title: trim((string) ($data['title'] ?? 'Normal Person')),
            email: ! empty($data['email']) ? trim((string) $data['email']) : null,
            normalPhone: ! empty($data['normalPhone']) ? trim((string) $data['normalPhone']) : null,
            whatsappPhone: ! empty($data['whatsappPhone']) ? trim((string) $data['whatsappPhone']) : null,
            status: (string) ($status ?: 'active'),
            division: $division ? (string) $division : null,
            pledgeAmount: (float) ($data['pledgeAmount'] ?? 0.0),
            paidAmount: (float) ($data['paidAmount'] ?? 0.0),
        );
    }

    /**
     * Attributes for Invitee model insertion.
     *
     * @return array<string, mixed>
     */
    public function toModelAttributes(): array
    {
        return [
            'eventId' => $this->eventId,
            'fullName' => $this->fullName,
            'title' => $this->title,
            'email' => $this->email,
            'normalPhone' => $this->normalPhone,
            'whatsappPhone' => $this->whatsappPhone,
            'status' => $this->status,
            'division' => $this->division,
        ];
    }
}
