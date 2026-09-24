<?php

use App\Enums\CheckInStatus;
use App\Enums\EntryType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\EntryActivity;
use App\Models\Event;
use App\Models\EventOwner;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createGateKeeperTestOwner(): array
{
    $owner = EventOwner::create([
        'firstName' => 'Salum',
        'lastName' => 'Rashid',
        'email' => 'salum_'.uniqid().'@iventpro.com',
        'phone' => '+255716'.rand(100000, 999999),
        'eventIds' => [],
    ]);

    $user = User::create([
        'name' => 'Salum Rashid',
        'username' => 'salum_'.uniqid(),
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

function createGateKeeperTestEvent(int $ownerId): Event
{
    return Event::create([
        'title' => 'VIP Gala Night',
        'slug' => 'vip-gala-'.uniqid(),
        'eventCategory' => 'normal',
        'eventOwnerId' => $ownerId,
        'venueName' => 'Dar Serena Hotel',
        'startDate' => '2026-12-31',
        'endDate' => '2026-12-31',
        'inviteeLimit' => 200,
        'status' => 'published',
    ]);
}

test('event owner can create a gatekeeper and issue access PIN credentials', function () {
    [$ownerUser, $owner] = createGateKeeperTestOwner();
    $event = createGateKeeperTestEvent($owner->id);

    Sanctum::actingAs($ownerUser);

    $response = $this->postJson("/api/v1/events/{$event->id}/gate-keepers", [
        'fullName' => 'Kassim Scanner',
        'phone' => '+255755998877',
        'pin' => '456789',
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.gatekeeper.fullName', 'Kassim Scanner')
        ->assertJsonPath('data.accessPin', '456789');

    $this->assertDatabaseHas('gateKeepers', [
        'fullName' => 'Kassim Scanner',
        'phone' => '+255755998877',
    ]);

    $this->assertDatabaseHas('users', [
        'role' => UserRole::GateKeeper->value,
        'phone' => '+255755998877',
    ]);
});

test('gatekeeper can authenticate via PIN and receive sanctum token', function () {
    [$ownerUser, $owner] = createGateKeeperTestOwner();
    $event = createGateKeeperTestEvent($owner->id);

    Sanctum::actingAs($ownerUser);

    $createResponse = $this->postJson("/api/v1/events/{$event->id}/gate-keepers", [
        'fullName' => 'Kassim Scanner',
        'phone' => '+255755998877',
        'pin' => '123456',
    ]);

    $createResponse->assertCreated();

    // Authenticate as gatekeeper using public PIN auth endpoint
    $authResponse = $this->postJson('/api/v1/gate-keepers/auth', [
        'identifier' => '+255755998877',
        'pin' => '123456',
    ]);

    $authResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'token',
                'user',
                'gateKeeper',
                'assignedEventIds',
            ],
        ]);
});

test('gatekeeper can fetch event attendee manifest', function () {
    [$ownerUser, $owner] = createGateKeeperTestOwner();
    $event = createGateKeeperTestEvent($owner->id);

    // Create invitees
    Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Hon. Joseph Mbowe',
        'normalPhone' => '+255788111222',
        'status' => 'confirmed',
        'entryPassCode' => 'VIP-001',
    ]);
    Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Dr. Anna Tibaijuka',
        'normalPhone' => '+255788333444',
        'status' => 'pending',
        'entryPassCode' => 'VIP-002',
    ]);

    Sanctum::actingAs($ownerUser);

    $response = $this->getJson("/api/v1/gate-keepers/events/{$event->id}/manifest");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.totalManifest', 2)
        ->assertJsonCount(2, 'data.invitees');
});

test('event owner can view live entry activities feed', function () {
    [$ownerUser, $owner] = createGateKeeperTestOwner();
    $event = createGateKeeperTestEvent($owner->id);

    $invitee = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Hellen Grace',
        'normalPhone' => '+255788555666',
        'status' => 'confirmed',
        'entryPassCode' => 'VIP-777',
    ]);

    EntryActivity::create([
        'eventId' => $event->id,
        'entryType' => EntryType::Invitee,
        'inviteeId' => $invitee->id,
        'scannedCode' => 'VIP-777',
        'status' => CheckInStatus::Success,
        'gateName' => 'VIP Main Gate',
        'scannedAt' => now(),
    ]);

    Sanctum::actingAs($ownerUser);

    $response = $this->getJson("/api/v1/events/{$event->id}/gate/activities");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.data');
});
