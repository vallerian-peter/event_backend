<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Models\EventFeatureBalance;
use App\Models\Invitee;
use App\Models\MessageRecord;
use App\Services\Messaging\BeemSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Background job for asynchronous SMS dispatching via Beem Africa.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 60];
    }

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly ?int $messageRecordId = null,
    ) {
        $this->onQueue('messages');
        $this->afterCommit = true;
    }

    /**
     * Execute the job.
     */
    public function handle(BeemSmsService $smsService): void
    {
        $result = $smsService->sendSms($this->phone, $this->message);

        // Update message audit record if tracking is enabled
        if ($this->messageRecordId) {
            $record = MessageRecord::find($this->messageRecordId);
            if ($record) {
                $isSuccess = (bool) ($result['isSuccessful'] ?? false);
                $record->update([
                    'status' => $isSuccess ? MessageStatus::Sent : MessageStatus::Failed,
                    'errorMessage' => $isSuccess ? null : ($result['error'] ?? 'Unknown error'),
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
                    // Send failed; refund consumed feature balance if applicable
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

                // Refund consumed balance if applicable
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
