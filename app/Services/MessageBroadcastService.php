<?php

namespace App\Services;

use App\DTOs\Messaging\BroadcastMessageDTO;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\UserRole;
use App\Jobs\SendSmsJob;
use App\Jobs\SendWhatsappJob;
use App\Models\Event;
use App\Models\EventFeatureBalance;
use App\Models\Invitee;
use App\Models\Message;
use App\Models\MessageRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessageBroadcastService
{
    /**
     * Broadcast an on-demand message to event invitees.
     *
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function broadcast(BroadcastMessageDTO $dto, ?User $actor = null): array
    {
        $event = Event::findOrFail($dto->eventId);
        $this->authorizeAccess($event, $actor);

        // 1. Resolve eligible recipients
        $query = Invitee::where('eventId', $event->id);

        if (! empty($dto->inviteeIds)) {
            $query->whereIn('id', $dto->inviteeIds);
        }

        if ($dto->division) {
            $query->where('division', $dto->division);
        }

        if ($dto->status) {
            $query->where('status', $dto->status);
        }

        if ($dto->contributionStatus) {
            $query->where('contributionStatus', $dto->contributionStatus);
        }

        $allMatching = $query->get();

        // Filter those with valid destination numbers
        $recipients = $allMatching->filter(function (Invitee $invitee) use ($dto) {
            if ($dto->channel === MessageChannel::Whatsapp) {
                return ! empty($invitee->whatsappPhone) || ! empty($invitee->normalPhone);
            }

            return ! empty($invitee->normalPhone);
        });

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'invitees' => ['No eligible invitees with valid phone numbers match the selected criteria.'],
            ]);
        }

        $recipientCount = $recipients->count();
        $featureKey = ($dto->channel === MessageChannel::Whatsapp) ? 'whatsappUnits' : 'smsUnits';
        $provider = ($dto->channel === MessageChannel::Whatsapp) ? 'infobip' : 'beem';

        // 2 & 3. Persist Message, MessageRecords and deduct quota atomically in transaction
        return DB::transaction(function () use ($event, $dto, $recipients, $recipientCount, $featureKey, $provider) {
            $balance = EventFeatureBalance::where('eventId', $event->id)
                ->where('featureKey', $featureKey)
                ->lockForUpdate()
                ->first();

            if ($balance) {
                $available = $balance->grantedUnits - $balance->consumedUnits - $balance->reservedUnits;
                if ($available < $recipientCount) {
                    throw ValidationException::withMessages([
                        'balance' => [
                            "Insufficient {$featureKey} quota. Required: {$recipientCount}, Available: {$available}. Please top up your package balance.",
                        ],
                    ]);
                }
            }

            $batchId = (string) Str::uuid();

            /** @var Message $message */
            $message = Message::create([
                'eventId' => $event->id,
                'invitees' => $recipients->pluck('id')->all(),
                'status' => 'queued',
                'message' => $dto->message,
            ]);

            foreach ($recipients as $invitee) {
                $phone = ($dto->channel === MessageChannel::Whatsapp)
                    ? ($invitee->whatsappPhone ?: $invitee->normalPhone)
                    : $invitee->normalPhone;

                // Format phone number
                $cleanedPhone = preg_replace('/\D/', '', $phone ?? '');
                if (str_starts_with($cleanedPhone, '0')) {
                    $cleanedPhone = '255'.substr($cleanedPhone, 1);
                } elseif (! str_starts_with($cleanedPhone, '255')) {
                    $cleanedPhone = '255'.$cleanedPhone;
                }

                $record = MessageRecord::create([
                    'batchId' => $batchId,
                    'eventId' => $event->id,
                    'inviteeId' => $invitee->id,
                    'featureKey' => $featureKey,
                    'messagePurpose' => 'broadcast',
                    'channel' => $dto->channel,
                    'units' => 1,
                    'status' => MessageStatus::Pending,
                    'provider' => $provider,
                    'idempotencyKey' => "broadcast:{$event->id}:{$invitee->id}:{$batchId}",
                    'payload' => [
                        'message' => $dto->message,
                        'recipientName' => $invitee->fullName,
                        'phone' => $cleanedPhone,
                    ],
                    'reservedAt' => now(),
                ]);

                // Dispatch job based on channel
                if ($dto->channel === MessageChannel::Whatsapp) {
                    $template = $dto->templateName ?? 'general_announcement';
                    SendWhatsappJob::dispatch(
                        $cleanedPhone,
                        $template,
                        $dto->placeholders ?: [$invitee->fullName, $event->title, $dto->message],
                        'en_US',
                        $record->id
                    );
                } else {
                    SendSmsJob::dispatch($cleanedPhone, $dto->message, $record->id);
                }
            }

            // Deduct units from feature balance
            if ($balance) {
                $balance->increment('consumedUnits', $recipientCount);
            }

            return [
                'batchId' => $batchId,
                'messageId' => $message->id,
                'channel' => $dto->channel->value,
                'recipientCount' => $recipientCount,
                'status' => 'queued',
            ];
        });
    }

    /**
     * Get campaign messages for an event.
     */
    public function getEventMessages(int $eventId, ?User $actor = null, int $perPage = 15): LengthAwarePaginator
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeAccess($event, $actor);

        return Message::where('eventId', $eventId)
            ->latest('createdAt')
            ->paginate($perPage);
    }

    /**
     * Get delivery logs for an event.
     */
    public function getDeliveryRecords(int $eventId, ?User $actor = null, int $perPage = 50): LengthAwarePaginator
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeAccess($event, $actor);

        return MessageRecord::where('eventId', $eventId)
            ->latest('createdAt')
            ->paginate($perPage);
    }

    /**
     * Get feature balances for an event.
     *
     * @return array<string, mixed>
     */
    public function getFeatureBalances(int $eventId, ?User $actor = null): array
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeAccess($event, $actor);

        $balances = EventFeatureBalance::where('eventId', $eventId)->get();

        $result = [];
        foreach ($balances as $b) {
            $available = max(0, $b->grantedUnits - $b->consumedUnits - $b->reservedUnits);
            $result[$b->featureKey] = [
                'granted' => $b->grantedUnits,
                'consumed' => $b->consumedUnits,
                'reserved' => $b->reservedUnits,
                'available' => $available,
            ];
        }

        // Return defaults if not set
        if (! isset($result['smsUnits'])) {
            $result['smsUnits'] = ['granted' => 0, 'consumed' => 0, 'reserved' => 0, 'available' => 0];
        }
        if (! isset($result['whatsappUnits'])) {
            $result['whatsappUnits'] = ['granted' => 0, 'consumed' => 0, 'reserved' => 0, 'available' => 0];
        }

        return $result;
    }

    /**
     * Authorize user access to the event.
     *
     * @throws AuthorizationException
     */
    protected function authorizeAccess(Event $event, ?User $actor): void
    {
        if (! $actor) {
            return;
        }

        if ($actor->role === UserRole::Admin) {
            return;
        }

        if ($actor->role === UserRole::EventOwner && (int) $actor->roleId === (int) $event->eventOwnerId) {
            return;
        }

        throw new AuthorizationException('You are not authorized to send messages for this event.');
    }
}
