<?php

namespace App\Console\Commands;

use App\Services\EventReminderService;
use Illuminate\Console\Command;

class SendEventAutoRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:send-auto-reminders
                            {--event= : Optional specific event ID to process}
                            {--dry-run : Simulate reminder evaluation without dispatching messages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and dispatch automated contribution reminders and near-event gate pass invitations';

    /**
     * Execute the console command.
     */
    public function handle(EventReminderService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $specificEventId = $this->option('event') ? (int) $this->option('event') : null;

        $this->info('Starting automated event reminders processing...'.($dryRun ? ' [DRY RUN MODE]' : ''));

        $results = $service->processAllReminders($dryRun, $specificEventId);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Events Evaluated', $results['processedEvents']],
                ['Contribution Reminders Dispatched', $results['sentReminders']],
                ['Entry Pass Invitations Dispatched', $results['sentInvitations']],
                ['Recipients Skipped (Not Due / No Phone)', $results['skipped']],
                ['Dry Run Simulation', $results['dryRun'] ? 'Yes' : 'No'],
            ]
        );

        $this->info('Automated reminders execution completed.');

        return Command::SUCCESS;
    }
}
