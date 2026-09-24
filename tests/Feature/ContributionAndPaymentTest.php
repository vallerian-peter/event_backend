<?php

use App\Enums\ContributionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Contribution;
use App\Models\Event;
use App\Models\EventOwner;
use App\Models\EventsPayment;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createTestPaymentUser(): array
{
    $owner = EventOwner::create([
        'firstName' => 'Amina',
        'lastName' => 'Bakari',
        'email' => 'amina_'.uniqid().'@iventpro.com',
        'phone' => '+255714'.rand(100000, 999999),
        'eventIds' => [],
    ]);

    $user = User::create([
        'name' => 'Amina Bakari',
        'username' => 'amina_'.uniqid(),
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

function createTestPaymentEvent(int $ownerId): Event
{
    return Event::create([
        'title' => 'Amina Graduation Party',
        'slug' => 'amina-grad-'.uniqid(),
        'eventCategory' => 'normal',
        'eventOwnerId' => $ownerId,
        'venueName' => 'Mlimani City Hall',
        'startDate' => '2026-12-20',
        'endDate' => '2026-12-20',
        'inviteeLimit' => 50,
        'contributionsNeeded' => true,
        'divisions' => 'both',
        'status' => 'published',
    ]);
}

test('it records a manual contribution and updates invitee status to paid', function () {
    [$user, $owner] = createTestPaymentUser();
    Sanctum::actingAs($user);

    $event = createTestPaymentEvent($owner->id);
    $invitee = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Saidi Ally',
        'normalPhone' => '+255712000001',
        'contributionStatus' => ContributionStatus::Pending,
    ]);

    $payload = [
        'eventId' => $event->id,
        'inviteeId' => $invitee->id,
        'pledgeAmount' => 50000,
        'paidAmount' => 50000,
        'division' => 'single',
        'transactionId' => 'CASH-001',
    ];

    $response = $this->postJson('/api/v1/contributions', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.pledgeAmount', 50000)
        ->assertJsonPath('data.paidAmount', 50000)
        ->assertJsonPath('data.status', 'paid');

    $this->assertDatabaseHas('contributions', [
        'eventId' => $event->id,
        'inviteeId' => $invitee->id,
        'status' => 'paid',
    ]);

    $this->assertEquals(ContributionStatus::Paid, $invitee->fresh()->contributionStatus);
});

test('it lists contributions with pagination and status filters', function () {
    [$user, $owner] = createTestPaymentUser();
    Sanctum::actingAs($user);

    $event = createTestPaymentEvent($owner->id);

    Contribution::create([
        'eventId' => $event->id,
        'pledgeAmount' => '100000',
        'paidAmount' => '100000',
        'status' => ContributionStatus::Paid,
    ]);

    Contribution::create([
        'eventId' => $event->id,
        'pledgeAmount' => '50000',
        'paidAmount' => '0',
        'status' => ContributionStatus::Pending,
    ]);

    $response = $this->getJson("/api/v1/contributions?eventId={$event->id}&status=paid");

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.contributions.0.status', 'paid');
});

test('it updates and deletes a contribution and recalculates invitee status', function () {
    [$user, $owner] = createTestPaymentUser();
    Sanctum::actingAs($user);

    $event = createTestPaymentEvent($owner->id);
    $invitee = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Khadija Said',
        'contributionStatus' => ContributionStatus::Pending,
    ]);

    $contribution = Contribution::create([
        'eventId' => $event->id,
        'inviteeId' => $invitee->id,
        'pledgeAmount' => '50000',
        'paidAmount' => '20000',
        'status' => ContributionStatus::Pending,
    ]);

    $this->assertEquals(ContributionStatus::Pending, $invitee->fresh()->contributionStatus);

    // Update to full payment
    $updateRes = $this->putJson("/api/v1/contributions/{$contribution->id}", [
        'paidAmount' => 50000,
        'status' => 'paid',
    ]);

    $updateRes->assertStatus(200)
        ->assertJsonPath('data.paidAmount', 50000);

    $this->assertEquals(ContributionStatus::Paid, $invitee->fresh()->contributionStatus);

    // Delete contribution
    $delRes = $this->deleteJson("/api/v1/contributions/{$contribution->id}");
    $delRes->assertStatus(200);

    $this->assertDatabaseMissing('contributions', ['id' => $contribution->id]);
    $this->assertEquals(ContributionStatus::Pending, $invitee->fresh()->contributionStatus);
});

test('it initiates a shallow payment checkout returning provider response and reference', function () {
    [$user, $owner] = createTestPaymentUser();
    Sanctum::actingAs($user);

    $event = createTestPaymentEvent($owner->id);
    $invitee = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Juma Mwamburi',
    ]);

    $payload = [
        'purpose' => 'contribution',
        'amount' => 30000,
        'currency' => 'TZS',
        'paymentMethod' => 'azampay',
        'mobileNumber' => '0714123456',
        'eventId' => $event->id,
        'inviteeId' => $invitee->id,
    ];

    $response = $this->postJson('/api/v1/payments/checkout', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.amount', 30000)
        ->assertJsonPath('data.currency', 'TZS');

    $reference = $response->json('data.reference');
    $transactionId = $response->json('data.transactionId');

    $this->assertNotNull($reference);
    $this->assertNotNull($transactionId);

    $this->assertDatabaseHas('eventsPayments', [
        'transactionId' => $transactionId,
        'amount' => '30000',
        'status' => 'pending',
    ]);
});

test('it processes webhook callback, marks payment completed, and updates linked contribution', function () {
    [$user, $owner] = createTestPaymentUser();
    Sanctum::actingAs($user);

    $event = createTestPaymentEvent($owner->id);
    $invitee = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Rashid Bakari',
        'contributionStatus' => ContributionStatus::Pending,
    ]);

    // Create checkout
    $checkoutRes = $this->postJson('/api/v1/payments/checkout', [
        'purpose' => 'contribution',
        'amount' => 45000,
        'currency' => 'TZS',
        'paymentMethod' => 'mpesa',
        'mobileNumber' => '0714999888',
        'eventId' => $event->id,
        'inviteeId' => $invitee->id,
    ]);

    $transactionId = $checkoutRes->json('data.transactionId');

    // Simulate inbound webhook callback
    $webhookPayload = [
        'transactionId' => $transactionId,
        'amount' => 45000,
        'status' => 'completed',
    ];

    $webhookRes = $this->postJson('/api/v1/payments/webhook', $webhookPayload);

    $webhookRes->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.paymentStatus', 'paid');

    $this->assertDatabaseHas('eventsPayments', [
        'transactionId' => $transactionId,
        'status' => 'paid',
        'paidAmount' => '45000',
    ]);

    $this->assertDatabaseHas('contributions', [
        'transactionId' => $transactionId,
        'status' => 'paid',
        'paidAmount' => '45000',
    ]);

    $this->assertEquals(ContributionStatus::Paid, $invitee->fresh()->contributionStatus);

    // Query payment status
    $statusRes = $this->getJson("/api/v1/payments/{$transactionId}/status");
    $statusRes->assertStatus(200)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.amount', 45000);
});
