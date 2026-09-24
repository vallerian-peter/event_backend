<?php

namespace App\DTOs\Invitee;

/**
 * Data Transfer Object for aggregated event invitees statistics.
 */
class InviteeStatsResponseDTO
{
    public function __construct(
        public readonly int $total,
        public readonly int $active,
        public readonly int $confirmed,
        public readonly int $pending,
        public readonly int $declined,
        public readonly int $attended,
        public readonly float $totalPledged,
        public readonly float $totalPaid,
        public readonly int $smsDelivered,
        public readonly int $smsPending,
        public readonly int $smsFailed,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'active' => $this->active,
            'confirmed' => $this->confirmed,
            'pending' => $this->pending,
            'declined' => $this->declined,
            'attended' => $this->attended,
            'totalPledged' => $this->totalPledged,
            'totalPaid' => $this->totalPaid,
            'smsDelivered' => $this->smsDelivered,
            'smsPending' => $this->smsPending,
            'smsFailed' => $this->smsFailed,
        ];
    }
}
