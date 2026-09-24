<?php

use App\Enums\EventCategory;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\EventOwner;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createTicketingTestOwner(): array
{
    $owner = EventOwner::create([
        'firstName' => 'Neema',
        'lastName' => 'Massawe',
        'email' => 'neema_'.uniqid().'@iventpro.com',
        'phone' => '+255717'.rand(100000, 999999),
        'eventIds' => [],
    ]);

    $user = User::create([
        'name' => 'Neema Massawe',
        'username' => 'neema_'.uniqid(),
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

function createTicketingTestEvent(int $ownerId): Event
{
    return Event::create([
        'title' => 'Serengeti Music Festival',
        'slug' => 'serengeti-fest-'.uniqid(),
        'eventCategory' => EventCategory::Ticketed,
        'eventOwnerId' => $ownerId,
        'venueName' => 'Posta Grounds, Dar',
        'startDate' => '2026-11-20',
        'endDate' => '2026-11-20',
        'isPublic' => true,
        'status' => 'published',
    ]);
}

test('event owner can create, update, list and delete ticket types', function () {
    [$user, $owner] = createTicketingTestOwner();
    $event = createTicketingTestEvent($owner->id);

    Sanctum::actingAs($user);

    // Create Ticket Type
    $createResponse = $this->postJson("/api/v1/events/{$event->id}/ticket-types", [
        'name' => 'Early Bird VIP',
        'description' => 'Includes front row access and complimentary drinks',
        'price' => 50000,
        'currency' => 'TZS',
        'quantityTotal' => 100,
        'minPerOrder' => 1,
        'maxPerOrder' => 5,
        'isActive' => true,
    ]);

    $createResponse->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Early Bird VIP')
        ->assertJsonPath('data.price', '50000.00');

    $ticketTypeId = $createResponse->json('data.id');

    // Update Ticket Type
    $updateResponse = $this->putJson("/api/v1/ticket-types/{$ticketTypeId}", [
        'price' => 60000,
        'name' => 'VIP Golden Circle',
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'VIP Golden Circle')
        ->assertJsonPath('data.price', '60000.00');

    // List Ticket Types
    $listResponse = $this->getJson("/api/v1/events/{$event->id}/ticket-types");
    $listResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data');

    // Delete Ticket Type
    $deleteResponse = $this->deleteJson("/api/v1/ticket-types/{$ticketTypeId}");
    $deleteResponse->assertOk();

    $this->assertDatabaseMissing('ticket_types', ['id' => $ticketTypeId]);
});

test('public users can discover public events catalog and event by slug', function () {
    [$user, $owner] = createTicketingTestOwner();
    $event = createTicketingTestEvent($owner->id);

    TicketType::create([
        'eventId' => $event->id,
        'name' => 'Regular Entry',
        'price' => 20000,
        'quantityTotal' => 500,
        'quantitySold' => 0,
        'isActive' => true,
    ]);

    // Public catalog list
    $catalogResponse = $this->getJson('/api/v1/public/events');
    $catalogResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['data' => ['data']]);

    // Public event details by slug
    $slugResponse = $this->getJson("/api/v1/public/events/{$event->slug}");
    $slugResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.event.title', 'Serengeti Music Festival')
        ->assertJsonCount(1, 'data.ticketTypes');
});

test('public user can checkout tickets and receive valid ticket entries', function () {
    [$user, $owner] = createTicketingTestOwner();
    $event = createTicketingTestEvent($owner->id);

    $ticketType = TicketType::create([
        'eventId' => $event->id,
        'name' => 'Standard Pass',
        'price' => 25000,
        'quantityTotal' => 100,
        'quantitySold' => 0,
        'minPerOrder' => 1,
        'maxPerOrder' => 10,
        'isActive' => true,
    ]);

    $checkoutResponse = $this->postJson("/api/v1/public/events/{$event->id}/tickets/checkout", [
        'ticketTypeId' => $ticketType->id,
        'quantity' => 2,
        'customerName' => 'Faraja Mwenda',
        'customerEmail' => 'faraja@example.com',
        'customerPhone' => '+255767123456',
        'paymentMethod' => 'azampay',
    ]);

    $checkoutResponse->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.order.customerName', 'Faraja Mwenda')
        ->assertJsonPath('data.order.totalAmount', '50000.00')
        ->assertJsonPath('data.paymentStatus', 'COMPLETED')
        ->assertJsonCount(2, 'data.tickets');

    $this->assertDatabaseHas('ticket_orders', [
        'customerEmail' => 'faraja@example.com',
        'status' => OrderStatus::Paid->value,
    ]);

    // Check quantitySold was updated
    expect($ticketType->fresh()->quantitySold)->toBe(2);
});

test('gatekeeper can verify and check in ticket with duplicate prevention', function () {
    [$user, $owner] = createTicketingTestOwner();
    $event = createTicketingTestEvent($owner->id);

    $ticketType = TicketType::create([
        'eventId' => $event->id,
        'name' => 'General Ticket',
        'price' => 10000,
        'quantityTotal' => 50,
        'quantitySold' => 1,
        'isActive' => true,
    ]);

    $order = \App\Models\TicketOrder::create([
        'eventId' => $event->id,
        'customerName' => 'Zuhura Bakari',
        'customerEmail' => 'zuhura@example.com',
        'customerPhone' => '+255711223344',
        'totalAmount' => 10000,
        'paidAmount' => 10000,
        'currency' => 'TZS',
        'paymentMethod' => 'azampay',
        'status' => OrderStatus::Paid,
    ]);

    $ticket = Ticket::create([
        'orderId' => $order->id,
        'ticketTypeId' => $ticketType->id,
        'eventId' => $event->id,
        'holderName' => 'Zuhura Bakari',
        'holderEmail' => 'zuhura@example.com',
        'holderPhone' => '+255711223344',
        'status' => TicketStatus::Valid,
    ]);

    // Public or scanner verify ticket
    $verifyResponse = $this->getJson("/api/v1/tickets/{$ticket->ticketNumber}/verify");
    $verifyResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.ticketNumber', $ticket->ticketNumber);

    // Gate check-in
    Sanctum::actingAs($user);

    $checkInResponse = $this->postJson("/api/v1/tickets/{$ticket->ticketNumber}/check-in", [
        'gateName' => 'North Gate',
    ]);

    $checkInResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.ticket.status', TicketStatus::Used->value);

    // Re-scanning must fail with duplicate check-in validation
    $duplicateResponse = $this->postJson("/api/v1/tickets/{$ticket->ticketNumber}/check-in", [
        'gateName' => 'North Gate',
    ]);

    $duplicateResponse->assertStatus(422)
        ->assertJsonPath('status', 'error');
});
