<?php

namespace App\Jobs;

use App\Services\Messaging\BrevoEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Background job for asynchronous transactional email delivery via Brevo.
 */
class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $recipientName,
        public readonly string $subject,
        public readonly string $htmlContent,
    ) {
        $this->onQueue('emails');
    }

    public function handle(BrevoEmailService $emailService): void
    {
        $emailService->sendTransactionalEmail(
            $this->recipientEmail,
            $this->recipientName,
            $this->subject,
            $this->htmlContent
        );
    }
}
