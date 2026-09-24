<?php

namespace App\DTOs\Messaging;

use App\Enums\MessageChannel;
use App\Http\Requests\Messaging\BroadcastMessageRequest;

class BroadcastMessageDTO
{
    /**
     * @param  int[]|null  $inviteeIds
     * @param  array<string>  $placeholders
     */
    public function __construct(
        public readonly int $eventId,
        public readonly MessageChannel $channel,
        public readonly string $message,
        public readonly ?string $templateName = null,
        public readonly array $placeholders = [],
        public readonly ?string $division = null,
        public readonly ?string $status = null,
        public readonly ?string $contributionStatus = null,
        public readonly ?array $inviteeIds = null,
    ) {}

    public static function fromRequest(BroadcastMessageRequest $request, int $eventId): self
    {
        $channel = MessageChannel::tryFrom($request->input('channel', 'sms')) ?? MessageChannel::Sms;

        return new self(
            eventId: $eventId,
            channel: $channel,
            message: $request->input('message'),
            templateName: $request->input('templateName'),
            placeholders: $request->input('placeholders', []),
            division: $request->input('division'),
            status: $request->input('status'),
            contributionStatus: $request->input('contributionStatus'),
            inviteeIds: $request->input('inviteeIds'),
        );
    }
}
