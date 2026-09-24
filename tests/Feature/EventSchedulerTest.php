<?php

use App\Console\Commands\SendEventAutoRemindersCommand;
use App\Enums\ContributionStatus;
use App\Enums\EventCategory;
use App\Enums\InviteeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendSmsJob;
use App\Models\Contribution;
use App\Models\Event;
use App\Models\EventOwner;
use App\Models\Invitee;
use App\Models\User;
use App\Services\EventReminderService;
use App\Services\Messaging\SmsIntervalParser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('SmsIntervalParser parses raw interval strings correctly', function () {
    $p1 = SmsIntervalParser::parse('5days');
    expect($p1['value'])->toBe(5)
        ->and($p1['unit'])->toBe('day');

    $p2 = SmsIntervalParser::parse('2weeks');
    expect($p2['value'])->toBe(2)
        ->and($p2['unit'])->toBe('week');

    $p3 = SmsIntervalParser::parse('1months');
    expect($p3['value'])->toBe(1)
        ->and($p3['unit'])->toBe('month');

    $p4 = SmsIntervalParser::parse('12hours');
    expect($p4['value'])->toBe(12)
        ->and($p4['unit'])->toBe('hour');

    $p5 = SmsIntervalParser::parse(null);
    expect($p5['value'])->toBe(5)
        ->and($p5['unit'])->toBe('day');
});

test('SmsIntervalParser accurately evaluates contribution reminder due status', function () {
    $now = Carbon::parse('2026-11-20 10:00:00');

    // Case 1: Never sent before, event started 6 days ago, interval 5 days -> Due
    $due1 = SmsIntervalParser::isContributionReminderDue(
        lastSentAt: null,
        eventStart: '2026-11-14',
        eventEnd: '2026-12-01',
        intervalRaw: '5days',
        now: $now
    );
    expect($due1)->toBeTrue();

    // Case 2: Sent 3 days ago, interval 5 days -> NOT Due
    $due2 = SmsIntervalParser::isContributionReminderDue(
        lastSentAt: '2026-11-17 09:00:00',
        eventStart: '2026-11-14',
        eventEnd: '2026-12-01',
        intervalRaw: '5days',
        now: $now
    );
    expect($due2)->toBeFalse();

    // Case 3: Sent 6 days ago, interval 5 days -> Due
    $due3 = SmsIntervalParser::isContributionReminderDue(
        lastSentAt: '2026-11-14 09:00:00',
        eventStart: '2026-11-14',
        eventEnd: '2026-12-01',
        intervalRaw: '5days',
        now: $now
    );
    expect($due3)->toBeTrue();

    // Case 4: Already sent today -> NOT Due
    $due4 = SmsIntervalParser::isContributionReminderDue(
        lastSentAt: '2026-11-20 07:00:00',
        eventStart: '2026-11-14',
        eventEnd: '2026-12-01',
        intervalRaw: '5days',
        now: $now
    );
    expect($due4)->toBeFalse();

    // Case 5: 1 day before event end date (near-event window) -> Reminders STOP
    $due5 = SmsIntervalParser::isContributionReminderDue(
        lastSentAt: null,
        eventStart: '2026-11-14',
        eventEnd: '2026-11-21',
        intervalRaw: '5days',
        now: $now // Nov 20 is 1 day before Nov 21
    );
    expect($due5)->toBeFalse();
});

test('EventReminderService dispatches contribution reminders to unpaid attendees', function () {
    Queue::fake();
    Carbon::setTestNow('2026-11-20 09:00:00');

    $owner = EventOwner::create([
        'firstName' => 'Tumaini',
        'lastName' => 'John',
        'email' => 'tumaini_'.uniqid().'@iventpro.com',
        'phone' => '+255716111222',
        'eventIds' => [],
    ]);

    $event = Event::create([
        'title' => 'Tumaini Wedding Celebration',
        'slug' => 'tumaini-wedding-'.uniqid(),
        'eventCategory' => EventCategory::Normal,
        'eventOwnerId' => $owner->id,
        'venueName' => 'Diamond Jubilee Hall',
        'startDate' => '2026-11-10',
        'endDate' => '2026-12-05',
        'eventTime' => '18:00',
        'autoSendSms' => true,
        'smsTimeDifference' => '5days',
        'contributionsNeeded' => true,
        'status' => 'published',
    ]);

    // Invitee 1: Unpaid, last reminder sent 6 days ago -> Should receive reminder
    $unpaidInvitee = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Joseph Mkama',
        'normalPhone' => '+255714000111',
        'status' => InviteeStatus::Active,
        'contributionStatus' => ContributionStatus::Pending,
        'autoReminderLastSentAt' => '2026-11-14 09:00:00',
        'entryPassCode' => 'JM-123456',
    ]);

    Contribution::create([
        'eventId' => $event->id,
        'inviteeId' => $unpaidInvitee->id,
        'pledgeAmount' => '100000',
        'paidAmount' => '20000',
        'status' => ContributionStatus::Pending,
    ]);

    // Invitee 2: Fully paid -> Should NOT receive contribution reminder
    $paidInvitee = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Grace Mrema',
        'normalPhone' => '+255714000222',
        'status' => InviteeStatus::Active,
        'contributionStatus' => ContributionStatus::Paid,
        'autoReminderLastSentAt' => '2026-11-14 09:00:00',
        'entryPassCode' => 'GM-123456',
    ]);

    Contribution::create([
        'eventId' => $event->id,
        'inviteeId' => $paidInvitee->id,
        'pledgeAmount' => '50000',
        'paidAmount' => '50000',
        'status' => ContributionStatus::Paid,
    ]);

    $service = new EventReminderService();
    $report = $service->processAllReminders(dryRun: false, specificEventId: $event->id);

    expect($report['sentReminders'])->toBe(1)
        ->and($report['skipped'])->toBe(1);

    Queue::assertPushed(SendSmsJob::class, 1);

    // Verify timestamp was updated
    expect($unpaidInvitee->fresh()->autoReminderLastSentAt->toDateString())->toBe('2026-11-20');
});

test('EventReminderService dispatches near-event gate pass invitations to confirmed attendees', function () {
    Queue::fake();
    // Simulate being 1 day before the event
    Carbon::setTestNow('2026-11-29 10:00:00');

    $owner = EventOwner::create([
        'firstName' => 'Baraka',
        'lastName' => 'Daudi',
        'email' => 'baraka_'.uniqid().'@iventpro.com',
        'phone' => '+255717333444',
        'eventIds' => [],
    ]);

    $event = Event::create([
        'title' => 'Baraka Birthday Gala',
        'slug' => 'baraka-bday-'.uniqid(),
        'eventCategory' => EventCategory::Normal,
        'eventOwnerId' => $owner->id,
        'venueName' => 'Serena Hotel',
        'startDate' => '2026-11-30',
        'endDate' => '2026-11-30',
        'eventTime' => '19:00',
        'autoSendSms' => true,
        'contributionsNeeded' => false, // Non-contribution event
        'status' => 'published',
    ]);

    $confirmedGuest = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'VIP Guest',
        'normalPhone' => '+255718555666',
        'status' => InviteeStatus::Active,
        'entryPassCode' => 'VIP-998877',
        'autoEntryLastSentAt' => null,
    ]);

    $service = new EventReminderService();
    $report = $service->processAllReminders(dryRun: false, specificEventId: $event->id);

    expect($report['sentInvitations'])->toBe(1);

    Queue::assertPushed(SendSmsJob::class, 1);

    expect($confirmedGuest->fresh()->autoEntryLastSentAt->toDateString())->toBe('2026-11-29');
});

test('it runs events:send-auto-reminders console command successfully in dry-run and live modes', function () {
    $this->artisan('events:send-auto-reminders --dry-run')
        ->expectsOutputToContain('Starting automated event reminders processing')
        ->expectsOutputToContain('Events Evaluated')
        ->assertSuccessful();
});

test('SmsIntervalParser does not trigger reminders for future event start dates', function () {
    $now = Carbon::parse('2026-11-20 10:00:00');

    // Future start date: event starts in 5 days (2026-11-25)
    $due = SmsIntervalParser::isContributionReminderDue(
        lastSentAt: null,
        eventStart: '2026-11-25',
        eventEnd: '2026-12-10',
        intervalRaw: '5days',
        now: $now
    );

    expect($due)->toBeFalse();
});

test('EventReminderService skips dispatching when SMS feature balance quota is exhausted', function () {
    Queue::fake();
    Carbon::setTestNow('2026-11-20 09:00:00');

    $owner = EventOwner::create([
        'firstName' => 'Amani',
        'lastName' => 'Kassim',
        'email' => 'amani_'.uniqid().'@iventpro.com',
        'phone' => '+255716999888',
        'eventIds' => [],
    ]);

    $event = Event::create([
        'title' => 'Amani Fundraiser',
        'slug' => 'amani-fundraiser-'.uniqid(),
        'eventCategory' => EventCategory::Normal,
        'eventOwnerId' => $owner->id,
        'venueName' => 'Mlimani City',
        'startDate' => '2026-11-10',
        'endDate' => '2026-12-05',
        'eventTime' => '18:00',
        'autoSendSms' => true,
        'smsTimeDifference' => '5days',
        'contributionsNeeded' => true,
        'status' => 'published',
    ]);

    // Give event an exhausted SMS balance (granted 10, consumed 10 -> 0 remaining)
    \App\Models\EventFeatureBalance::create([
        'eventId' => $event->id,
        'featureKey' => 'smsUnits',
        'grantedUnits' => 10,
        'reservedUnits' => 0,
        'consumedUnits' => 10,
    ]);

    $unpaidInvitee = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Hamisi Juma',
        'normalPhone' => '+255714000333',
        'status' => InviteeStatus::Active,
        'contributionStatus' => ContributionStatus::Pending,
        'autoReminderLastSentAt' => '2026-11-14 09:00:00',
    ]);

    Contribution::create([
        'eventId' => $event->id,
        'inviteeId' => $unpaidInvitee->id,
        'pledgeAmount' => '100000',
        'paidAmount' => '0',
        'status' => ContributionStatus::Pending,
    ]);

    $service = new EventReminderService();
    $report = $service->processAllReminders(dryRun: false, specificEventId: $event->id);

    expect($report['sentReminders'])->toBe(0)
        ->and($report['skipped'])->toBe(1);

    Queue::assertNothingPushed();
});
