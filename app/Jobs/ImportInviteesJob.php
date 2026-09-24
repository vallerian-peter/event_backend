<?php

namespace App\Jobs;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job to process large Excel/CSV guest list imports without blocking HTTP requests.
 */
class ImportInviteesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $eventId,
        public readonly string $filePath,
    ) {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $event = Event::find($this->eventId);
        if (! $event) {
            Log::warning("ImportInviteesJob: Event #{$this->eventId} not found.");

            return;
        }

        Log::info("Processing guest import for Event #{$event->id} from file: {$this->filePath}");
    }
}
