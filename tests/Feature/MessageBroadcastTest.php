<?php

use App\Enums\EventDivision;
use App\Enums\InviteeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendSmsJob;
use App\Jobs\SendWhatsappJob;
use App\Models\Event;
use App\Models\EventFeatureBalance;
use App\Models\EventOwner;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createTestMessagingUser(): array
{
    $owner = EventOwner::create([
        'firstName' => 'Moses',
        'lastName' => 'Kiyabo',
        'email' => 'moses_'.uniqid().'@iventpro.com',
        'phone' => '+255715'.rand(100000, 999999),
        'eventIds' => [],
    ]);

    $user = User::create([
        'name' => 'Moses Kiyabo',
        'username' => 'moses_'.uniqid(),
        'email' => $owner->email,
        'phone' => $owner->phone,
        'password' => Hash::make('Secret123'),
        'role' => UserRole::EventOwner,
        'roleId' => $owner->id,
        'status' => UserStatus::Active,
    ]);

    $owner->update(['userId' => $user->id]);

    return [$user, $owner];
}

function createTestMessagingEvent(int $ownerId): Event
{
    return Event::create([
        'title' => 'Moses Corporate Gala',
        'slug' => 'moses-gala-'.uniqid(),
        'eventCategory' => 'normal',
        'eventOwnerId' => $ownerId,
        'venueName' => 'Johari Rotana',
        'startDate' => '2026-12-10',
        'endDate' => '2026-12-10',
        'inviteeLimit' => 100,
        'contributionsNeeded' => false,
        'divisions' => 'both',
        'status' => 'published',
    ]);
}

test('it queues an on-demand SMS broadcast to all event invitees', function () {
    Queue::fake();

    [$user, $owner] = createTestMessagingUser();
    Sanctum::actingAs($user);

    $event = createTestMessagingEvent($owner->id);

    Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Participant One',
        'normalPhone' => '+255712111001',
        'status' => InviteeStatus::Active,
    ]);

    Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Participant Two',
        'normalPhone' => '+255712111002',
        'status' => InviteeStatus::Active,
    ]);

    // Give event feature balance
    EventFeatureBalance::create([
        'eventId' => $event->id,
        'featureKey' => 'smsUnits',
        'grantedUnits' => 100,
        'reservedUnits' => 0,
        'consumedUnits' => 0,
    ]);

    $payload = [
        'channel' => 'sms',
        'message' => 'Welcome to the gala! Registration starts at 6 PM.',
    ];

    $response = $this->postJson("/api/v1/events/{$event->id}/messages/send", $payload);

    $response->assertStatus(202)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.recipientCount', 2)
        ->assertJsonPath('data.channel', 'sms');

    Queue::assertPushed(SendSmsJob::class, 2);

    $this->assertDatabaseHas('messages', [
        'eventId' => $event->id,
        'message' => 'Welcome to the gala! Registration starts at 6 PM.',
    ]);

    $this->assertDatabaseHas('message_records', [
        'eventId' => $event->id,
        'channel' => 'sms',
        'messagePurpose' => 'broadcast',
    ]);

    // Check balance deducted
    $balance = EventFeatureBalance::where('eventId', $event->id)->where('featureKey', 'smsUnits')->first();
    $this->assertEquals(2, $balance->consumedUnits);
});

test('it rejects broadcast when feature balance quota is insufficient', function () {
    [$user, $owner] = createTestMessagingUser();
    Sanctum::actingAs($user);

    $event = createTestMessagingEvent($owner->id);

    Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Participant One',
        'normalPhone' => '+255712111001',
    ]);

    Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Participant Two',
        'normalPhone' => '+255712111002',
    ]);

    // Give event balance of only 1 unit
    EventFeatureBalance::create([
        'eventId' => $event->id,
        'featureKey' => 'smsUnits',
        'grantedUnits' => 1,
        'reservedUnits' => 0,
        'consumedUnits' => 0,
    ]);

    $payload = [
        'channel' => 'sms',
        'message' => 'Insufficient quota test message',
    ];

    $response = $this->postJson("/api/v1/events/{$event->id}/messages/send", $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['balance']);
});

test('it broadcasts WhatsApp message with template parameters to filtered division', function () {
    Queue::fake();

    [$user, $owner] = createTestMessagingUser();
    Sanctum::actingAs($user);

    $event = createTestMessagingEvent($owner->id);

    $singleGuest = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Single Guest',
        'whatsappPhone' => '+255799111222',
        'division' => EventDivision::Single,
    ]);

    $doubleGuest = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Double Guest',
        'whatsappPhone' => '+255799333444',
        'division' => EventDivision::Double,
    ]);

    $payload = [
        'channel' => 'whatsapp',
        'division' => 'single',
        'message' => 'Single seat confirmation notice.',
        'templateName' => 'single_ticket_notice',
    ];

    $response = $this->postJson("/api/v1/events/{$event->id}/messages/send", $payload);

    $response->assertStatus(202)
        ->assertJsonPath('data.recipientCount', 1);

    Queue::assertPushed(SendWhatsappJob::class, 1);
});

test('it retrieves message campaign history and delivery records', function () {
    [$user, $owner] = createTestMessagingUser();
    Sanctum::actingAs($user);

    $event = createTestMessagingEvent($owner->id);

    $invitee = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Audited Attendee',
        'normalPhone' => '+255711222333',
    ]);

    $this->postJson("/api/v1/events/{$event->id}/messages/send", [
        'channel' => 'sms',
        'message' => 'History check message',
    ]);

    // Check campaigns list
    $campaignsRes = $this->getJson("/api/v1/events/{$event->id}/messages");
    $campaignsRes->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.meta.total', 1);

    // Check delivery records list
    $recordsRes = $this->getJson("/api/v1/events/{$event->id}/messages/records");
    $recordsRes->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.meta.total', 1);

    // Check feature balances
    $balanceRes = $this->getJson("/api/v1/events/{$event->id}/features/balance");
    $balanceRes->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['data' => ['smsUnits', 'whatsappUnits']]);
});
