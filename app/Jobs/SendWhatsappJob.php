<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\EventFeatureBalance;
use App\Models\Invitee;
use App\Models\MessageRecord;
use App\Services\Messaging\InfobipWhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Background job for asynchronous WhatsApp dispatching via InfoBip.
 */
class SendWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 60];
    }

    /**
     * @param  array<string>  $placeholders
     */
    public function __construct(
        public readonly string $phone,
        public readonly string $templateName,
        public readonly array $placeholders = [],
        public readonly string $language = 'en_US',
        public readonly ?int $messageRecordId = null,
    ) {
        $this->onQueue('messages');
        $this->afterCommit = true;
    }

    /**
     * Execute the job.
     */
    public function handle(InfobipWhatsappService $whatsappService): void
    {
        $result = $whatsappService->sendTemplateMessage(
            $this->phone,
            $this->templateName,
            $this->placeholders,
            $this->language
        );

        if ($this->messageRecordId) {
            $record = MessageRecord::find($this->messageRecordId);
            if ($record) {
                $isSuccess = (bool) ($result['successful'] ?? false);
                $record->update([
                    'status' => $isSuccess ? MessageStatus::Sent : MessageStatus::Failed,
                    'errorMessage' => $isSuccess ? null : ($result['error'] ?? 'WhatsApp delivery failed'),
                    'sentAt' => $isSuccess ? now() : null,
                    'failedAt' => $isSuccess ? null : now(),
                ]);

                if ($isSuccess) {
                    if ($record->inviteeId) {
                        if ($record->messagePurpose === 'contribution_reminder') {
                            Invitee::where('id', $record->inviteeId)->update(['autoReminderLastSentAt' => now()]);
                        } elseif ($record->messagePurpose === 'entry_invitation') {
                            Invitee::where('id', $record->inviteeId)->update(['autoEntryLastSentAt' => now()]);
                        }
                    }
                } else {
                    if ($record->eventId && $record->featureKey) {
                        EventFeatureBalance::where('eventId', $record->eventId)
                            ->where('featureKey', $record->featureKey)
                            ->where('consumedUnits', '>', 0)
                            ->decrement('consumedUnits', $record->units ?? 1);
                    }
                }
            }
        }
    }

    /**
     * Handle job failure when retries are exhausted or an unhandled exception occurs.
     */
    public function failed(?Throwable $exception): void
    {
        if ($this->messageRecordId) {
            $record = MessageRecord::find($this->messageRecordId);
            if ($record && $record->status === MessageStatus::Pending) {
                $record->update([
                    'status' => MessageStatus::Failed,
                    'errorMessage' => $exception ? $exception->getMessage() : 'Exhausted maximum retry attempts',
                    'failedAt' => now(),
                ]);

                if ($record->eventId && $record->featureKey) {
                    EventFeatureBalance::where('eventId', $record->eventId)
                        ->where('featureKey', $record->featureKey)
                        ->where('consumedUnits', '>', 0)
                        ->decrement('consumedUnits', $record->units ?? 1);
                }
            }
        }
    }
}
