<?php

namespace App\DTOs\Invitee;

use App\Enums\EventDivision;
use App\Enums\InviteeStatus;
use Illuminate\Http\Request;

/**
 * Data Transfer Object for updating an existing Invitee.
 */
class UpdateInviteeDTO
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly array $attributes,
        public readonly ?float $pledgeAmount = null,
        public readonly ?float $paidAmount = null,
        public readonly ?string $division = null,
    ) {}

    /**
     * Create DTO from validated Form Request or HTTP Request.
     */
    public static function fromRequest(Request $request): self
    {
        $attributes = [];

        $directKeys = [
            'fullName',
            'title',
            'email',
            'normalPhone',
            'whatsappPhone',
        ];

        foreach ($directKeys as $key) {
            if ($request->exists($key)) {
                $attributes[$key] = $request->filled($key) ? trim((string) $request->input($key)) : null;
            }
        }

        if ($request->exists('status') || $request->exists('inviteStatus')) {
            $status = $request->input('status', $request->input('inviteStatus'));
            if ($status instanceof InviteeStatus) {
                $status = $status->value;
            }
            $attributes['status'] = (string) $status;
        }

        if ($request->exists('division')) {
            $division = $request->input('division');
            if ($division instanceof EventDivision) {
                $division = $division->value;
            }
            $attributes['division'] = $division ? (string) $division : null;
        }

        $pledgeAmount = $request->exists('pledgeAmount') ? (float) $request->input('pledgeAmount') : null;
        $paidAmount = $request->exists('paidAmount') ? (float) $request->input('paidAmount') : null;
        $division = $request->exists('division') ? (string) $request->input('division') : null;

        return new self(
            attributes: $attributes,
            pledgeAmount: $pledgeAmount,
            paidAmount: $paidAmount,
            division: $division,
        );
    }
}
