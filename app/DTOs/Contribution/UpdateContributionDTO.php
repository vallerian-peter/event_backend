<?php

namespace App\DTOs\Contribution;

use App\Enums\ContributionStatus;
use App\Http\Requests\Contribution\UpdateContributionRequest;

class UpdateContributionDTO
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly array $attributes,
    ) {}

    public static function fromRequest(UpdateContributionRequest $request): self
    {
        $attributes = [];

        if ($request->has('pledgeAmount')) {
            $attributes['pledgeAmount'] = (string) $request->input('pledgeAmount');
        }
        if ($request->has('paidAmount')) {
            $attributes['paidAmount'] = (string) $request->input('paidAmount');
        }
        if ($request->has('division')) {
            $attributes['division'] = $request->input('division');
        }
        if ($request->has('transactionId')) {
            $attributes['transactionId'] = $request->input('transactionId');
        }
        if ($request->has('status')) {
            $status = ContributionStatus::tryFrom($request->input('status'));
            if ($status) {
                $attributes['status'] = $status;
            }
        }

        return new self($attributes);
    }
}
