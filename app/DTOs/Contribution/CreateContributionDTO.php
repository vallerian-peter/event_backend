<?php

namespace App\DTOs\Contribution;

use App\Enums\ContributionStatus;
use App\Http\Requests\Contribution\CreateContributionRequest;

class CreateContributionDTO
{
    public function __construct(
        public readonly int $eventId,
        public readonly ?int $inviteeId,
        public readonly float $pledgeAmount,
        public readonly float $paidAmount,
        public readonly ?string $division = null,
        public readonly ?string $transactionId = null,
        public readonly ?ContributionStatus $status = null,
    ) {}

    public static function fromRequest(CreateContributionRequest $request): self
    {
        $status = null;
        if ($request->has('status')) {
            $status = ContributionStatus::tryFrom($request->input('status'));
        }

        return new self(
            eventId: (int) $request->input('eventId'),
            inviteeId: $request->has('inviteeId') ? (int) $request->input('inviteeId') : null,
            pledgeAmount: (float) $request->input('pledgeAmount', 0),
            paidAmount: (float) $request->input('paidAmount', 0),
            division: $request->input('division'),
            transactionId: $request->input('transactionId'),
            status: $status,
        );
    }
}
