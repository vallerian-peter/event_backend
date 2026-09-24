<?php

namespace App\Jobs;

use App\Models\Invitee;
use App\Models\Template;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job to render customized QR invitation cards and store them on Cloudflare R2.
 */
class GenerateInvitationCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $inviteeId,
        public readonly int $templateId,
    ) {
        $this->onQueue('cards');
    }

    public function handle(): void
    {
        $invitee = Invitee::find($this->inviteeId);
        $template = Template::with('elements')->find($this->templateId);

        if (! $invitee || ! $template) {
            Log::warning("GenerateInvitationCardJob: Invitee #{$this->inviteeId} or Template #{$this->templateId} not found.");

            return;
        }

        // Logic will hook into the card rendering engine
        Log::info("Generating card for Invitee #{$invitee->id} ({$invitee->fullName}) using Template #{$template->id}");
    }
}
