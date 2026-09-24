<?php

use App\Enums\CommitteeRole;
use App\Enums\CommitteeTitle;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CommitteeMeeting;
use App\Models\Event;
use App\Models\EventCommitteeMember;
use App\Models\EventOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createCommitteeTestUser(): array
{
    $owner = EventOwner::create([
        'firstName' => 'Baraka',
        'lastName' => 'Juma',
        'email' => 'baraka_'.uniqid().'@iventpro.com',
        'phone' => '+255715'.rand(100000, 999999),
        'eventIds' => [],
    ]);

    $user = User::create([
        'name' => 'Baraka Juma',
        'username' => 'baraka_'.uniqid(),
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

function createCommitteeTestEvent(int $ownerId): Event
{
    return Event::create([
        'title' => 'Baraka Wedding Celebration',
        'slug' => 'baraka-wedding-'.uniqid(),
        'eventCategory' => 'normal',
        'eventOwnerId' => $ownerId,
        'venueName' => 'Serena Hotel',
        'startDate' => '2026-11-15',
        'endDate' => '2026-11-15',
        'inviteeLimit' => 100,
        'contributionsNeeded' => true,
        'status' => 'published',
    ]);
}

test('event owner can add and list committee members', function () {
    [$user, $owner] = createCommitteeTestUser();
    $event = createCommitteeTestEvent($owner->id);

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/events/{$event->id}/committee/members", [
        'fullName' => 'John Doe',
        'normalPhone' => '+255788111222',
        'role' => CommitteeRole::Finance->value,
        'title' => CommitteeTitle::Elder->value,
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.fullName', 'John Doe')
        ->assertJsonPath('data.role', CommitteeRole::Finance->value);

    $listResponse = $this->getJson("/api/v1/events/{$event->id}/committee/members");
    $listResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data');
});

test('event owner can update and delete a committee member', function () {
    [$user, $owner] = createCommitteeTestUser();
    $event = createCommitteeTestEvent($owner->id);

    $member = EventCommitteeMember::create([
        'eventId' => $event->id,
        'fullName' => 'Jane Doe',
        'normalPhone' => '+255788333444',
        'role' => CommitteeRole::Logistics,
        'title' => CommitteeTitle::Friend,
    ]);

    Sanctum::actingAs($user);

    $updateResponse = $this->putJson("/api/v1/events/{$event->id}/committee/members/{$member->id}", [
        'fullName' => 'Jane Smith',
        'role' => CommitteeRole::Decoration->value,
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.fullName', 'Jane Smith')
        ->assertJsonPath('data.role', CommitteeRole::Decoration->value);

    $deleteResponse = $this->deleteJson("/api/v1/events/{$event->id}/committee/members/{$member->id}");
    $deleteResponse->assertOk()
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseMissing('event_committee_members', ['id' => $member->id]);
});

test('event owner can schedule meetings and add notes', function () {
    [$user, $owner] = createCommitteeTestUser();
    $event = createCommitteeTestEvent($owner->id);

    Sanctum::actingAs($user);

    $meetingResponse = $this->postJson("/api/v1/events/{$event->id}/committee/meetings", [
        'title' => 'First Budget Review',
        'meetingDate' => '2026-10-01',
        'meetingTime' => '18:00',
        'location' => 'Zoom & Serena Lounge',
        'agenda' => 'Review budget line items and targets',
    ]);

    $meetingResponse->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'First Budget Review');

    $meetingId = $meetingResponse->json('data.id');

    // Add note to meeting
    $noteResponse = $this->postJson("/api/v1/events/{$event->id}/committee/meetings/{$meetingId}/notes", [
        'content' => 'Agreed on 15M target for catering and decoration.',
    ]);

    $noteResponse->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.content', 'Agreed on 15M target for catering and decoration.');

    // Fetch meeting details
    $detailResponse = $this->getJson("/api/v1/events/{$event->id}/committee/meetings/{$meetingId}");
    $detailResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.notes');
});

test('event owner can send meeting reminder broadcast', function () {
    [$user, $owner] = createCommitteeTestUser();
    $event = createCommitteeTestEvent($owner->id);

    // Create 2 committee members
    EventCommitteeMember::create([
        'eventId' => $event->id,
        'fullName' => 'Member One',
        'normalPhone' => '+255788111111',
        'role' => CommitteeRole::Finance,
        'title' => CommitteeTitle::Friend,
    ]);
    EventCommitteeMember::create([
        'eventId' => $event->id,
        'fullName' => 'Member Two',
        'normalPhone' => '+255788222222',
        'role' => CommitteeRole::Logistics,
        'title' => CommitteeTitle::Elder,
    ]);

    $meeting = CommitteeMeeting::create([
        'eventId' => $event->id,
        'title' => 'Midweek Prep Meeting',
        'meetingDate' => '2026-10-05',
        'meetingTime' => '19:00',
        'location' => 'Physical: Peacock Hotel',
        'agenda' => 'Confirm transport',
    ]);

    Sanctum::actingAs($user);

    $remindResponse = $this->postJson("/api/v1/events/{$event->id}/committee/meetings/{$meeting->id}/remind", [
        'channel' => 'sms',
        'customMessage' => 'Hello {name}, reminder for our meeting on {date} at {time}.',
    ]);

    $remindResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.sentCount', 2);
});
