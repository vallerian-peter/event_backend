<?php

namespace App\Services;

use App\Enums\ContributionStatus;
use App\Enums\EventLanguage;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Jobs\SendSmsJob;
use App\Models\Event;
use App\Models\EventFeatureBalance;
use App\Models\Invitee;
use App\Models\MessageRecord;
use App\Services\Messaging\SmsIntervalParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventReminderService
{
    /**
     * Process automated reminders and gate-pass invitations for all active events.
     *
     * @return array{
     *     processedEvents: int,
     *     sentReminders: int,
     *     sentInvitations: int,
     *     skipped: int,
     *     dryRun: bool,
     *     details: array<int, array<string, mixed>>
     * }
     */
    public function processAllReminders(bool $dryRun = false, ?int $specificEventId = null): array
    {
        $query = Event::query()
            ->where('autoSendSms', true);

        if ($specificEventId) {
            $query->where('id', $specificEventId);
        }

        $events = $query->get();

        $stats = [
            'processedEvents' => 0,
            'sentReminders' => 0,
            'sentInvitations' => 0,
            'skipped' => 0,
            'dryRun' => $dryRun,
            'details' => [],
        ];

        foreach ($events as $event) {
            $eventStats = $this->processEvent($event, $dryRun);
            $stats['processedEvents']++;
            $stats['sentReminders'] += $eventStats['sentReminders'];
            $stats['sentInvitations'] += $eventStats['sentInvitations'];
            $stats['skipped'] += $eventStats['skipped'];
            $stats['details'][$event->id] = $eventStats;
        }

        return $stats;
    }

    /**
     * Process reminders for a single event.
     *
     * @return array{
     *     eventId: int,
     *     eventTitle: string,
     *     sentReminders: int,
     *     sentInvitations: int,
     *     skipped: int,
     *     actions: array<int, array<string, mixed>>
     * }
     */
    public function processEvent(Event $event, bool $dryRun = false): array
    {
        $eventStats = [
            'eventId' => $event->id,
            'eventTitle' => $event->title,
            'sentReminders' => 0,
            'sentInvitations' => 0,
            'skipped' => 0,
            'actions' => [],
        ];

        // Fetch active invitees with their contributions
        $invitees = Invitee::with('contributions')
            ->where('eventId', $event->id)
            ->where('status', 'active')
            ->get();

        $now = Carbon::now();
        $isNearEventWindow = SmsIntervalParser::isNearEventInvitationDue(
            $event->startDate,
            $event->eventTime ?? null,
            null, // general event-level window check
            $now
        );

        $smsBalance = EventFeatureBalance::where('eventId', $event->id)
            ->where('featureKey', 'smsUnits')
            ->first();
        $availableUnits = $smsBalance
            ? ($smsBalance->grantedUnits - $smsBalance->consumedUnits - $smsBalance->reservedUnits)
            : null;

        foreach ($invitees as $invitee) {
            // Check if phone number is present
            $phone = $invitee->normalPhone ?: $invitee->whatsappPhone;
            if (empty($phone)) {
                $eventStats['skipped']++;
                $eventStats['actions'][] = [
                    'inviteeId' => $invitee->id,
                    'name' => $invitee->fullName,
                    'action' => 'skipped',
                    'reason' => 'no_phone',
                ];
                continue;
            }

            // Clean phone
            $cleanedPhone = preg_replace('/\D/', '', $phone);
            if (str_starts_with($cleanedPhone, '0')) {
                $cleanedPhone = '255'.substr($cleanedPhone, 1);
            } elseif (! str_starts_with($cleanedPhone, '255')) {
                $cleanedPhone = '255'.$cleanedPhone;
            }

            // Calculate contribution progress
            $totalPledged = (float) $invitee->contributions->sum(fn ($c) => (float) $c->pledgeAmount);
            $totalPaid = (float) $invitee->contributions->sum(fn ($c) => (float) $c->paidAmount);
            $contributionsRequired = (bool) $event->contributionsNeeded;

            $isContributionComplete = ! $contributionsRequired || ($totalPaid >= $totalPledged && $totalPledged > 0) || ($invitee->contributionStatus === ContributionStatus::Paid);

            // ----------------------------------------------------
            // Stage 2: Near-Event Gate Code Invitation
            // ----------------------------------------------------
            if ($isNearEventWindow && empty($invitee->autoEntryLastSentAt) && $isContributionComplete && ! empty($invitee->entryPassCode)) {
                if ($smsBalance && $availableUnits !== null && $availableUnits <= 0) {
                    $eventStats['skipped']++;
                    $eventStats['actions'][] = [
                        'inviteeId' => $invitee->id,
                        'name' => $invitee->fullName,
                        'action' => 'skipped',
                        'reason' => 'insufficient_balance',
                    ];
                    continue;
                }

                $messageText = $this->buildEntryInvitationText($event, $invitee);

                if (! $dryRun) {
                    $this->dispatchAutomatedMessage($event, $invitee, $cleanedPhone, $messageText, 'entry_invitation', $smsBalance);
                    $invitee->update(['autoEntryLastSentAt' => $now]);
                    if ($availableUnits !== null) {
                        $availableUnits--;
                    }
                }

                $eventStats['sentInvitations']++;
                $eventStats['actions'][] = [
                    'inviteeId' => $invitee->id,
                    'name' => $invitee->fullName,
                    'action' => 'entry_invitation',
                    'phone' => $cleanedPhone,
                ];
                continue;
            }

            // ----------------------------------------------------
            // Stage 1: Contribution Reminder
            // ----------------------------------------------------
            if ($contributionsRequired && ! $isContributionComplete) {
                $isDue = SmsIntervalParser::isContributionReminderDue(
                    $invitee->autoReminderLastSentAt?->toISOString(),
                    $event->startDate,
                    $event->endDate ?? $event->startDate,
                    $event->smsTimeDifference ?? '5days',
                    $now
                );

                if ($isDue) {
                    if ($smsBalance && $availableUnits !== null && $availableUnits <= 0) {
                        $eventStats['skipped']++;
                        $eventStats['actions'][] = [
                            'inviteeId' => $invitee->id,
                            'name' => $invitee->fullName,
                            'action' => 'skipped',
                            'reason' => 'insufficient_balance',
                        ];
                        continue;
                    }

                    $messageText = $this->buildContributionReminderText($event, $invitee, $totalPledged, $totalPaid);

                    if (! $dryRun) {
                        $this->dispatchAutomatedMessage($event, $invitee, $cleanedPhone, $messageText, 'contribution_reminder', $smsBalance);
                        $invitee->update(['autoReminderLastSentAt' => $now]);
                        if ($availableUnits !== null) {
                            $availableUnits--;
                        }
                    }

                    $eventStats['sentReminders']++;
                    $eventStats['actions'][] = [
                        'inviteeId' => $invitee->id,
                        'name' => $invitee->fullName,
                        'action' => 'contribution_reminder',
                        'phone' => $cleanedPhone,
                    ];
                    continue;
                }
            }

            $eventStats['skipped']++;
        }

        return $eventStats;
    }

    /**
     * Dispatch automated message and record in audit log.
     */
    protected function dispatchAutomatedMessage(
        Event $event,
        Invitee $invitee,
        string $phone,
        string $messageText,
        string $purpose,
        ?EventFeatureBalance $balance
    ): void {
        $record = MessageRecord::create([
            'batchId' => (string) Str::uuid(),
            'eventId' => $event->id,
            'inviteeId' => $invitee->id,
            'featureKey' => 'smsUnits',
            'messagePurpose' => $purpose,
            'channel' => MessageChannel::Sms,
            'units' => 1,
            'status' => MessageStatus::Pending,
            'provider' => 'beem',
            'idempotencyKey' => "auto:{$event->id}:{$invitee->id}:{$purpose}:".Carbon::now()->format('Y-m-d'),
            'payload' => [
                'message' => $messageText,
                'recipientName' => $invitee->fullName,
                'phone' => $phone,
            ],
            'reservedAt' => now(),
        ]);

        SendSmsJob::dispatch($phone, $messageText, $record->id);

        if ($balance) {
            $balance->increment('consumedUnits', 1);
        }
    }

    /**
     * Build contribution reminder message text.
     */
    protected function buildContributionReminderText(Event $event, Invitee $invitee, float $pledge, float $paid): string
    {
        $isEnglish = ($event->eventLanguage ?? null) === EventLanguage::English;
        $remaining = max(0, $pledge - $paid);
        $amountFormatted = number_format($remaining, 0).' TZS';

        if ($isEnglish) {
            return "Contribution Reminder\n\nHello {$invitee->fullName}, you are kindly reminded of your contribution for {$event->title}.\n\nDate: {$event->startDate}\nVenue: {$event->venueName}\nBalance: {$amountFormatted}\n\nThank you for your support.";
        }

        return "Kumbusho la Mchango\n\nHabari {$invitee->fullName}, huu ni ukumbusho wa mchango wako kwa tukio la {$event->title}.\n\nTarehe: {$event->startDate}\nMahali: {$event->venueName}\nBaki la Mchango: {$amountFormatted}\n\nAsante kwa ushirikiano wako.";
    }

    /**
     * Build entry invitation gate pass message text.
     */
    protected function buildEntryInvitationText(Event $event, Invitee $invitee): string
    {
        $isEnglish = ($event->eventLanguage ?? null) === EventLanguage::English;

        if ($isEnglish) {
            return "Event Entry Pass\n\nHello {$invitee->fullName}, you are invited to attend {$event->title}.\n\nDate: {$event->startDate}\nVenue: {$event->venueName}\nTime: ".($event->eventTime ?: 'TBA')."\n\nGate Pass Code:\n{$invitee->entryPassCode}\n\nPlease present this code at the entrance.";
        }

        return "Nambari ya Kuingia Mlangoni\n\nHabari {$invitee->fullName}, unakaribishwa kuhudhuria {$event->title}.\n\nTarehe: {$event->startDate}\nMahali: {$event->venueName}\nMuda: ".($event->eventTime ?: 'TBA')."\n\nNambari ya Kuingia:\n{$invitee->entryPassCode}\n\nTafadhali wasilisha nambari hii mlangoni.";
    }
}
