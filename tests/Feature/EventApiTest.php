<?php

use App\Enums\EventStatus;
use App\Enums\TemplateRenderMode;
use App\Enums\TemplateType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\EventOwner;
use App\Models\SystemUser;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('r2');
});

function createTestOwnerUser(string $email = 'owner@iventpro.com', string $username = 'owneruser', ?string $phone = null): array
{
    $phone = $phone ?? ('+255712'.rand(100000, 999999));

    $owner = EventOwner::create([
        'firstName' => 'Amani',
        'middleName' => 'Juma',
        'lastName' => 'Massawe',
        'email' => $email,
        'phone' => $phone,
        'eventIds' => [],
    ]);

    $user = User::create([
        'name' => 'Amani Massawe',
        'username' => $username,
        'email' => $email,
        'phone' => $phone,
        'password' => Hash::make('Secret123'),
        'role' => UserRole::EventOwner,
        'roleId' => $owner->id,
        'status' => UserStatus::Active,
    ]);

    $owner->update(['userId' => $user->id]);

    return [$user, $owner];
}

function createTestAdminUser(): array
{
    $adminProfile = SystemUser::create([
        'firstName' => 'Admin',
        'lastName' => 'Super',
        'email' => 'admin@iventpro.com',
        'phone' => '+255700'.rand(100000, 999999),
    ]);

    $user = User::create([
        'name' => 'Admin Super',
        'username' => 'adminuser',
        'email' => 'admin@iventpro.com',
        'phone' => $adminProfile->phone,
        'password' => Hash::make('Secret123'),
        'role' => UserRole::Admin,
        'roleId' => $adminProfile->id,
        'status' => UserStatus::Active,
    ]);

    return [$user, $adminProfile];
}

function createTestTemplate(): Template
{
    return Template::create([
        'name' => 'Royal Wedding Floral Card',
        'pathUrl' => 'templates/cards/wedding-card-01.png',
        'previewImageUrl' => 'templates/previews/wedding-card-01.png',
        'category' => 'wedding',
        'price' => 15.00,
        'isPremium' => true,
        'templateType' => TemplateType::InvitationCard,
        'canvasWidth' => 800,
        'canvasHeight' => 1200,
        'renderMode' => TemplateRenderMode::QrAndName,
        'isActive' => true,
    ]);
}

/*
|--------------------------------------------------------------------------
| Event Creation Tests (Normal & Ticketed)
|--------------------------------------------------------------------------
*/

test('it creates a normal event with template, divisions, and links to owner', function () {
    [$user, $owner] = createTestOwnerUser();
    Sanctum::actingAs($user);

    $template = createTestTemplate();

    $payload = [
        'title' => 'Emmanuel & Neema Wedding Ceremony',
        'eventCategory' => 'normal',
        'typeOfEvent' => 'wedding',
        'venueName' => 'Mlimani City Conference Centre',
        'address' => 'Sam Nujoma Road, Dar es Salaam',
        'startDate' => '2026-11-20',
        'endDate' => '2026-11-20',
        'eventTime' => '18:00:00',
        'description' => 'Celebrating the holy matrimony of Emmanuel and Neema.',
        'contact1' => '+255712345678',
        'contact2' => '+255755123456',
        'templateId' => $template->id,
        'invitationTemplateTier' => 'premium',
        'contributionsNeeded' => true,
        'divisions' => 'both',
        'divisionsPrices' => [
            'single' => 50000,
            'double' => 100000,
            'both' => 150000,
        ],
        'servicePackageSlug' => 'smart_reminder',
        'inviteeLimit' => 300,
        'autoSendSms' => true,
        'smsTimeDifference' => '5days',
    ];

    $response = $this->postJson('/api/v1/events', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'Event created successfully.',
            'data' => [
                'title' => 'Emmanuel & Neema Wedding Ceremony',
                'eventCategory' => 'normal',
                'status' => 'draft',
                'typeOfEvent' => 'wedding',
                'venueName' => 'Mlimani City Conference Centre',
                'startDate' => '2026-11-20',
                'endDate' => '2026-11-20',
                'eventTime' => '18:00:00',
                'templateId' => $template->id,
                'contributionsNeeded' => true,
                'divisions' => 'both',
                'divisionsPrices' => [
                    'single' => 50000,
                    'double' => 100000,
                    'both' => 150000,
                ],
                'servicePackageSlug' => 'smart_reminder',
                'inviteeLimit' => 300,
                'eventOwnerId' => $owner->id,
            ],
        ]);

    $eventId = $response->json('data.id');
    $this->assertDatabaseHas('events', [
        'id' => $eventId,
        'eventOwnerId' => $owner->id,
        'eventCategory' => 'normal',
    ]);

    // Check that owner's eventIds array is synchronized
    $owner->refresh();
    expect($owner->eventIds)->toContain($eventId);
});

test('it creates a ticketed event with ticket tiers in transaction', function () {
    [$user, $owner] = createTestOwnerUser();
    Sanctum::actingAs($user);

    $payload = [
        'title' => 'Serengeti Tech & Music Festival 2026',
        'eventCategory' => 'ticketed',
        'typeOfEvent' => 'concert',
        'venueName' => 'Posta Grounds, Kijitonyama',
        'address' => 'Dar es Salaam, Tanzania',
        'startDate' => '2026-12-05',
        'endDate' => '2026-12-06',
        'eventTime' => '14:00:00',
        'description' => 'Biggest music and technology festival in East Africa.',
        'isPublic' => true,
        'isFeatured' => true,
        'category' => 'Technology & Music',
        'ticketTypes' => [
            [
                'name' => 'Early Bird Regular',
                'price' => 20000,
                'quantityTotal' => 500,
                'currency' => 'TZS',
                'minPerOrder' => 1,
                'maxPerOrder' => 5,
                'description' => 'General entrance access.',
            ],
            [
                'name' => 'VIP Lounge Pass',
                'price' => 100000,
                'quantityTotal' => 100,
                'currency' => 'TZS',
                'minPerOrder' => 1,
                'maxPerOrder' => 4,
                'description' => 'Includes VIP seating and complimentary drinks.',
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/events', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'title' => 'Serengeti Tech & Music Festival 2026',
                'eventCategory' => 'ticketed',
                'isPublic' => true,
                'isFeatured' => true,
            ],
        ]);

    $eventId = $response->json('data.id');
    $ticketTypes = $response->json('data.ticketTypes');

    expect($ticketTypes)->toHaveCount(2);
    expect($ticketTypes[0]['name'])->toBe('Early Bird Regular');
    expect($ticketTypes[0]['price'])->toEqual(20000);
    expect($ticketTypes[1]['name'])->toBe('VIP Lounge Pass');

    $this->assertDatabaseHas('ticket_types', [
        'eventId' => $eventId,
        'name' => 'VIP Lounge Pass',
        'price' => 100000.00,
    ]);
});

test('it creates an event with flyer upload directly to Cloudflare R2', function () {
    [$user, $owner] = createTestOwnerUser();
    Sanctum::actingAs($user);

    $flyer = UploadedFile::fake()->image('festival-flyer.png', 800, 1200);

    $payload = [
        'title' => 'Zanzibar Beach Party 2026',
        'eventCategory' => 'normal',
        'startDate' => '2026-10-15',
        'eventTime' => '20:00:00',
        'flyer' => $flyer,
    ];

    $response = $this->post('/api/v1/events', $payload, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(201);
    $flyerUrl = $response->json('data.flyerUrl');
    expect($flyerUrl)->not->toBeNull();
    expect($flyerUrl)->toContain('events/flyers');

    // Verify storage received the file
    $files = Storage::disk('r2')->allFiles('events/flyers');
    expect($files)->not->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Event Listing & Filtering Tests
|--------------------------------------------------------------------------
*/

test('it lists events scoped to the authenticated event owner', function () {
    [$owner1User, $owner1] = createTestOwnerUser('owner1@iventpro.com', 'owner1');
    [$owner2User, $owner2] = createTestOwnerUser('owner2@iventpro.com', 'owner2');

    Event::create([
        'title' => 'Event Owner 1 Celebration',
        'eventCategory' => 'normal',
        'eventOwnerId' => $owner1->id,
        'status' => 'draft',
    ]);

    Event::create([
        'title' => 'Event Owner 2 Conference',
        'eventCategory' => 'ticketed',
        'eventOwnerId' => $owner2->id,
        'status' => 'published',
    ]);

    Sanctum::actingAs($owner1User);

    $response = $this->getJson('/api/v1/events');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'events',
                'meta' => ['currentPage', 'perPage', 'total', 'lastPage'],
            ],
        ]);

    $events = $response->json('data.events');
    expect($events)->toHaveCount(1);
    expect($events[0]['title'])->toBe('Event Owner 1 Celebration');
});

test('it allows admin to list and filter all events across owners', function () {
    [$owner1User, $owner1] = createTestOwnerUser('o1@iventpro.com', 'o1');
    [$owner2User, $owner2] = createTestOwnerUser('o2@iventpro.com', 'o2');
    [$adminUser] = createTestAdminUser();

    Event::create(['title' => 'Tech Summit 2026', 'eventCategory' => 'ticketed', 'eventOwnerId' => $owner1->id]);
    Event::create(['title' => 'Charity Dinner', 'eventCategory' => 'normal', 'eventOwnerId' => $owner2->id]);

    Sanctum::actingAs($adminUser);

    // List all
    $response = $this->getJson('/api/v1/events');
    $response->assertStatus(200);
    expect($response->json('data.events'))->toHaveCount(2);

    // Filter by query
    $searchResponse = $this->getJson('/api/v1/events?query=Charity');
    $searchResponse->assertStatus(200);
    expect($searchResponse->json('data.events'))->toHaveCount(1);
    expect($searchResponse->json('data.events.0.title'))->toBe('Charity Dinner');

    // Filter by category
    $categoryResponse = $this->getJson('/api/v1/events?eventCategory=ticketed');
    $categoryResponse->assertStatus(200);
    expect($categoryResponse->json('data.events'))->toHaveCount(1);
    expect($categoryResponse->json('data.events.0.title'))->toBe('Tech Summit 2026');
});

/*
|--------------------------------------------------------------------------
| Show, Update, Delete Tests
|--------------------------------------------------------------------------
*/

test('it retrieves an event by id and by slug with strict camelCase keys', function () {
    [$user, $owner] = createTestOwnerUser();
    Sanctum::actingAs($user);

    $event = Event::create([
        'title' => 'Kigamboni Tech Hub Opening',
        'slug' => 'kigamboni-tech-hub-opening',
        'eventCategory' => 'ticketed',
        'status' => 'draft',
        'eventOwnerId' => $owner->id,
        'venueName' => 'Kigamboni Bridge Complex',
        'eventTime' => '10:00:00',
    ]);

    // Query by ID
    $byIdResponse = $this->getJson("/api/v1/events/{$event->id}");
    $byIdResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'id' => $event->id,
                'slug' => 'kigamboni-tech-hub-opening',
                'eventCategory' => 'ticketed',
                'venueName' => 'Kigamboni Bridge Complex',
            ],
        ]);

    // Query by Slug
    $bySlugResponse = $this->getJson('/api/v1/events/kigamboni-tech-hub-opening');
    $bySlugResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'id' => $event->id,
            ],
        ]);
});

test('it updates event details and status', function () {
    [$user, $owner] = createTestOwnerUser();
    Sanctum::actingAs($user);

    $event = Event::create([
        'title' => 'Old Event Title',
        'eventCategory' => 'normal',
        'status' => 'draft',
        'eventOwnerId' => $owner->id,
    ]);

    $updatePayload = [
        'title' => 'Updated Glorious Gala',
        'status' => 'published',
        'venueName' => 'Hyatt Regency Kilimanjaro',
        'divisions' => 'single',
        'divisionsPrices' => ['single' => 75000],
    ];

    $response = $this->putJson("/api/v1/events/{$event->id}", $updatePayload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'title' => 'Updated Glorious Gala',
                'status' => 'published',
                'venueName' => 'Hyatt Regency Kilimanjaro',
                'divisions' => 'single',
            ],
        ]);

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'title' => 'Updated Glorious Gala',
        'status' => 'published',
    ]);
});

test('it prevents unauthorized event owner from updating another owners event', function () {
    [$owner1User, $owner1] = createTestOwnerUser('owner1@iventpro.com', 'owner1');
    [$owner2User, $owner2] = createTestOwnerUser('owner2@iventpro.com', 'owner2');

    $event = Event::create([
        'title' => 'Owner 1 Private Event',
        'eventCategory' => 'normal',
        'eventOwnerId' => $owner1->id,
        'status' => 'draft',
    ]);

    Sanctum::actingAs($owner2User);

    $response = $this->putJson("/api/v1/events/{$event->id}", [
        'title' => 'Hacked Event Title',
    ]);

    $response->assertStatus(403);
});

test('it deletes an event and removes id from eventOwners.eventIds', function () {
    [$user, $owner] = createTestOwnerUser();
    Sanctum::actingAs($user);

    $event = Event::create([
        'title' => 'Temporary Event to Delete',
        'eventCategory' => 'normal',
        'eventOwnerId' => $owner->id,
    ]);

    $owner->update(['eventIds' => [$event->id, 999]]);

    $response = $this->deleteJson("/api/v1/events/{$event->id}");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Event deleted successfully.',
        ]);

    $this->assertDatabaseMissing('events', ['id' => $event->id]);

    $owner->refresh();
    expect($owner->eventIds)->not->toContain($event->id);
    expect($owner->eventIds)->toContain(999);
});

/*
|--------------------------------------------------------------------------
| Dedicated Flyer & Status Endpoints Tests
|--------------------------------------------------------------------------
*/

test('it uploads flyer image via dedicated flyer endpoint', function () {
    [$user, $owner] = createTestOwnerUser();
    Sanctum::actingAs($user);

    $event = Event::create([
        'title' => 'Event Needing Flyer',
        'eventCategory' => 'normal',
        'eventOwnerId' => $owner->id,
    ]);

    $image = UploadedFile::fake()->image('poster.jpg', 600, 800);

    $response = $this->post("/api/v1/events/{$event->id}/flyer", [
        'flyer' => $image,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['flyerUrl'],
        ]);

    $flyerUrl = $response->json('data.flyerUrl');
    expect($flyerUrl)->toContain("events/{$event->id}/flyers");

    $event->refresh();
    expect($event->flyerUrl)->toBe($flyerUrl);
});

test('it updates event status via dedicated status endpoint', function () {
    [$user, $owner] = createTestOwnerUser();
    Sanctum::actingAs($user);

    $event = Event::create([
        'title' => 'Event Ready to Publish',
        'eventCategory' => 'normal',
        'eventOwnerId' => $owner->id,
        'status' => 'draft',
    ]);

    $response = $this->patchJson("/api/v1/events/{$event->id}/status", [
        'status' => 'published',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'status' => 'published',
            ],
        ]);

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Published);
});

/*
|--------------------------------------------------------------------------
| Public Events Discovery Tests
|--------------------------------------------------------------------------
*/

test('it returns published public events without authentication', function () {
    [$user, $owner] = createTestOwnerUser();

    // Published & Public -> Should be visible
    Event::create([
        'title' => 'Public Tech Exhibition 2026',
        'slug' => 'public-tech-exhibition-2026',
        'eventCategory' => 'ticketed',
        'isPublic' => true,
        'status' => 'published',
        'eventOwnerId' => $owner->id,
        'startDate' => '2026-12-01',
    ]);

    // Draft & Public -> Should NOT be visible to public
    Event::create([
        'title' => 'Draft Secret Concert',
        'eventCategory' => 'ticketed',
        'isPublic' => true,
        'status' => 'draft',
        'eventOwnerId' => $owner->id,
    ]);

    // Published & Private -> Should NOT be visible to public
    Event::create([
        'title' => 'Private Executive Dinner',
        'eventCategory' => 'normal',
        'isPublic' => false,
        'status' => 'published',
        'eventOwnerId' => $owner->id,
    ]);

    $response = $this->getJson('/api/v1/events/public');

    $response->assertStatus(200);
    $events = $response->json('data.events');
    expect($events)->toHaveCount(1);
    expect($events[0]['title'])->toBe('Public Tech Exhibition 2026');
});
