<?php

use App\Enums\CheckInStatus;
use App\Enums\ContributionStatus;
use App\Enums\EventDivision;
use App\Enums\InviteeStatus;
use App\Enums\TemplateRenderMode;
use App\Enums\TemplateType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\GenerateInvitationCardJob;
use App\Models\Contribution;
use App\Models\EntryActivity;
use App\Models\Event;
use App\Models\EventOwner;
use App\Models\SystemUser;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('r2');
});

function createTestInviteeUser(): array
{
    $owner = EventOwner::create([
        'firstName' => 'Baraka',
        'lastName' => 'Mbowe',
        'email' => 'owner_'.uniqid().'@iventpro.com',
        'phone' => '+255713'.rand(100000, 999999),
        'eventIds' => [],
    ]);

    $user = User::create([
        'name' => 'Baraka Mbowe',
        'username' => 'owner_'.uniqid(),
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

function createTestEventForInvitees(int $ownerId, array $overrides = []): Event
{
    return Event::create(array_merge([
        'title' => 'Baraka & Joyce Wedding Reception',
        'slug' => 'baraka-joyce-'.uniqid(),
        'eventCategory' => 'normal',
        'eventOwnerId' => $ownerId,
        'venueName' => 'Serena Hotel, Dar es Salaam',
        'startDate' => '2026-12-15',
        'endDate' => '2026-12-15',
        'inviteeLimit' => 10,
        'contributionsNeeded' => true,
        'divisions' => 'both',
        'status' => 'published',
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Invitee Creation & Capacity Tests
|--------------------------------------------------------------------------
*/

test('it creates a single invitee with entry pass code, QR image on R2, and contribution', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $payload = [
        'eventId' => $event->id,
        'fullName' => 'Hon. John Magufuli',
        'title' => 'Honourable',
        'email' => 'guest@example.com',
        'normalPhone' => '+255711223344',
        'whatsappPhone' => '+255711223344',
        'division' => 'double',
        'pledgeAmount' => 100000,
        'paidAmount' => 50000,
        'status' => 'confirmed',
    ];

    $response = $this->postJson('/api/v1/invitees', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.fullName', 'Hon. John Magufuli')
        ->assertJsonPath('data.title', 'Honourable')
        ->assertJsonPath('data.division', 'double')
        ->assertJsonPath('data.pledgeAmount', 100000)
        ->assertJsonPath('data.paidAmount', 50000)
        ->assertJsonPath('data.contributionStatus', 'pending');

    $inviteeId = $response->json('data.id');
    expect($response->json('data.entryPassCode'))->not->toBeNull();
    expect($response->json('data.entryPassQrImageUrl'))->toContain("events/{$event->id}/entry-passes/{$inviteeId}.png");

    // Assert DB record
    $this->assertDatabaseHas('invitees', [
        'id' => $inviteeId,
        'eventId' => $event->id,
        'fullName' => 'Hon. John Magufuli',
        'contributionStatus' => 'pending',
    ]);

    // Assert Contribution DB record
    $this->assertDatabaseHas('contributions', [
        'inviteeId' => $inviteeId,
        'eventId' => $event->id,
        'pledgeAmount' => '100000',
        'paidAmount' => '50000',
        'status' => 'pending',
    ]);

    // Assert QR exists in fake R2 storage
    Storage::disk('r2')->assertExists("events/{$event->id}/entry-passes/{$inviteeId}.png");
});

test('it marks contributionStatus as paid when paidAmount meets or exceeds pledgeAmount', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $response = $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Salma Kikwete',
        'normalPhone' => '+255755123456',
        'pledgeAmount' => 200000,
        'paidAmount' => 200000,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.contributionStatus', 'paid')
        ->assertJsonPath('data.pledgeAmount', 200000)
        ->assertJsonPath('data.paidAmount', 200000);

    $inviteeId = $response->json('data.id');
    $this->assertDatabaseHas('contributions', [
        'inviteeId' => $inviteeId,
        'status' => 'paid',
    ]);
});

test('it enforces event inviteeLimit capacity guard and returns 409 with INVITEE_LIMIT_REACHED', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    // Event with limit of 1
    $event = createTestEventForInvitees($owner->id, ['inviteeLimit' => 1]);

    // 1st invitee - succeeds
    $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'First Guest',
        'normalPhone' => '+255711000001',
    ])->assertStatus(201);

    // 2nd invitee - exceeds capacity
    $response = $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Second Guest',
        'normalPhone' => '+255711000002',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('code', 'INVITEE_LIMIT_REACHED')
        ->assertJsonPath('message', "Invitee limit reached. Increase this event's invitee capacity to continue.");
});

test('it dispatches GenerateInvitationCardJob when event has a templateId', function () {
    Queue::fake();

    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $template = Template::create([
        'name' => 'Royal Card',
        'pathUrl' => 'templates/bg.png',
        'templateType' => TemplateType::InvitationCard,
        'canvasWidth' => 1080,
        'canvasHeight' => 1920,
        'renderMode' => TemplateRenderMode::QrAndName,
    ]);

    $event = createTestEventForInvitees($owner->id, ['templateId' => $template->id]);

    $response = $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Card Recipient',
        'normalPhone' => '+255711888999',
    ]);

    $response->assertStatus(201);
    $inviteeId = $response->json('data.id');

    Queue::assertPushed(GenerateInvitationCardJob::class, function ($job) use ($inviteeId, $template) {
        return $job->inviteeId === $inviteeId && $job->templateId === $template->id;
    });
});

/*
|--------------------------------------------------------------------------
| Invitee Listing, Show & Filtering Tests
|--------------------------------------------------------------------------
*/

test('it lists invitees filtered by eventId, status, and search query', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Amani Safari',
        'email' => 'amani@safari.com',
        'normalPhone' => '+255711111111',
        'status' => 'confirmed',
        'division' => 'single',
    ])->assertStatus(201);

    $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Zuhura Simba',
        'email' => 'zuhura@simba.com',
        'normalPhone' => '+255722222222',
        'status' => 'pending',
        'division' => 'double',
    ])->assertStatus(201);

    // List all for event
    $response = $this->getJson("/api/v1/invitees?eventId={$event->id}");
    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => [
                'invitees',
                'meta' => ['currentPage', 'perPage', 'total'],
            ],
        ]);
    expect(count($response->json('data.invitees')))->toBe(2);

    // Filter by status
    $confirmed = $this->getJson("/api/v1/invitees?eventId={$event->id}&status=confirmed");
    $confirmed->assertStatus(200);
    expect(count($confirmed->json('data.invitees')))->toBe(1);
    expect($confirmed->json('data.invitees.0.fullName'))->toBe('Amani Safari');

    // Search query
    $search = $this->getJson("/api/v1/invitees?eventId={$event->id}&search=Simba");
    $search->assertStatus(200);
    expect(count($search->json('data.invitees')))->toBe(1);
    expect($search->json('data.invitees.0.fullName'))->toBe('Zuhura Simba');
});

test('it retrieves single invitee details and latest contribution', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $createRes = $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Dr. Jane Goodall',
        'normalPhone' => '+255733333333',
        'pledgeAmount' => 500000,
        'paidAmount' => 250000,
    ]);

    $inviteeId = $createRes->json('data.id');

    $response = $this->getJson("/api/v1/invitees/{$inviteeId}");
    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.id', $inviteeId)
        ->assertJsonPath('data.fullName', 'Dr. Jane Goodall')
        ->assertJsonPath('data.pledgeAmount', 500000)
        ->assertJsonPath('data.paidAmount', 250000)
        ->assertJsonPath('data.contributionStatus', 'pending');
});

/*
|--------------------------------------------------------------------------
| Invitee Updating & Deletion Tests
|--------------------------------------------------------------------------
*/

test('it updates invitee details and recalculates contribution status when amounts change', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $createRes = $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Kassim Majaliwa',
        'normalPhone' => '+255744444444',
        'pledgeAmount' => 100000,
        'paidAmount' => 20000,
    ]);

    $inviteeId = $createRes->json('data.id');

    // Update paidAmount to 100000 (completing pledge)
    $response = $this->putJson("/api/v1/invitees/{$inviteeId}", [
        'paidAmount' => 100000,
        'status' => 'confirmed',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.paidAmount', 100000)
        ->assertJsonPath('data.contributionStatus', 'paid');

    $this->assertDatabaseHas('contributions', [
        'inviteeId' => $inviteeId,
        'paidAmount' => '100000',
        'status' => 'paid',
    ]);
});

test('it deletes invitee and cleans up QR image asset from R2', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $createRes = $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'To Be Deleted',
        'normalPhone' => '+255755555555',
    ]);

    $inviteeId = $createRes->json('data.id');
    $qrPath = "events/{$event->id}/entry-passes/{$inviteeId}.png";
    Storage::disk('r2')->assertExists($qrPath);

    $deleteRes = $this->deleteJson("/api/v1/invitees/{$inviteeId}");
    $deleteRes->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Invitee deleted successfully.');

    $this->assertDatabaseMissing('invitees', ['id' => $inviteeId]);
    $this->assertDatabaseMissing('contributions', ['inviteeId' => $inviteeId]);
    Storage::disk('r2')->assertMissing($qrPath);
});

/*
|--------------------------------------------------------------------------
| Dedicated QR Preview & Check-In Tests
|--------------------------------------------------------------------------
*/

test('it returns QR preview payload matching event-management-system contract', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $createRes = $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Preview Guest',
        'normalPhone' => '+255766666666',
    ]);

    $inviteeId = $createRes->json('data.id');

    $response = $this->getJson("/api/v1/invitees/{$inviteeId}/qr-preview");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('available', true)
        ->assertJsonPath('mode', 'stored_token')
        ->assertJsonPath('inviteeId', $inviteeId)
        ->assertJsonPath('fullName', 'Preview Guest')
        ->assertJsonStructure(['code', 'qrImageUrl']);
});

test('it performs gate check-in successfully and logs EntryActivity', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $createRes = $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Gate Visitor',
        'normalPhone' => '+255777777777',
    ]);

    $inviteeId = $createRes->json('data.id');

    $response = $this->postJson("/api/v1/invitees/{$inviteeId}/check-in", [
        'gateName' => 'VIP Gate 1',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'attended')
        ->assertJsonPath('data.fullName', 'Gate Visitor');

    $this->assertDatabaseHas('invitees', [
        'id' => $inviteeId,
        'status' => 'attended',
    ]);

    $this->assertDatabaseHas('entry_activities', [
        'inviteeId' => $inviteeId,
        'eventId' => $event->id,
        'status' => 'success',
        'gateName' => 'VIP Gate 1',
    ]);
});

test('it rejects duplicate check-in if entry pass already consumed', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $createRes = $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Single Entry Guest',
        'normalPhone' => '+255788888888',
    ]);

    $inviteeId = $createRes->json('data.id');

    // 1st check-in succeeds
    $this->postJson("/api/v1/invitees/{$inviteeId}/check-in")->assertStatus(200);

    // 2nd check-in rejected
    $duplicate = $this->postJson("/api/v1/invitees/{$inviteeId}/check-in");
    $duplicate->assertStatus(409)
        ->assertJsonPath('status', 'error')
        ->assertJsonValidationErrors(['entryPass']);

    $this->assertDatabaseHas('entry_activities', [
        'inviteeId' => $inviteeId,
        'status' => 'duplicate',
    ]);
});

/*
|--------------------------------------------------------------------------
| Bulk Operations & Statistics Tests
|--------------------------------------------------------------------------
*/

test('it bulk creates invitees with capacity validation and contribution creation', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id, ['inviteeLimit' => 50]);

    $bulkData = [
        'eventId' => $event->id,
        'invitees' => [
            [
                'fullName' => 'Batch Guest 1',
                'normalPhone' => '+255799000001',
                'pledgeAmount' => 50000,
                'paidAmount' => 50000,
            ],
            [
                'fullName' => 'Batch Guest 2',
                'normalPhone' => '+255799000002',
                'pledgeAmount' => 30000,
                'paidAmount' => 0,
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/invitees/bulk', $bulkData);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.count', 2);

    $this->assertDatabaseHas('invitees', ['fullName' => 'Batch Guest 1', 'contributionStatus' => 'paid']);
    $this->assertDatabaseHas('invitees', ['fullName' => 'Batch Guest 2', 'contributionStatus' => 'pending']);
});

test('it bulk updates status for selected invitees', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $res1 = $this->postJson('/api/v1/invitees', ['eventId' => $event->id, 'fullName' => 'Guest A', 'normalPhone' => '+255700111001']);
    $res2 = $this->postJson('/api/v1/invitees', ['eventId' => $event->id, 'fullName' => 'Guest B', 'normalPhone' => '+255700111002']);

    $ids = [$res1->json('data.id'), $res2->json('data.id')];

    $response = $this->patchJson('/api/v1/invitees/bulk-status', [
        'eventId' => $event->id,
        'inviteeIds' => $ids,
        'status' => 'confirmed',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.updatedCount', 2)
        ->assertJsonPath('data.status', 'confirmed');

    $this->assertDatabaseHas('invitees', ['id' => $ids[0], 'status' => 'confirmed']);
    $this->assertDatabaseHas('invitees', ['id' => $ids[1], 'status' => 'confirmed']);
});

test('it computes aggregated event invitee statistics', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Guest 1',
        'normalPhone' => '+255700222001',
        'pledgeAmount' => 100000,
        'paidAmount' => 50000,
        'status' => 'confirmed',
    ]);

    $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'Guest 2',
        'normalPhone' => '+255700222002',
        'pledgeAmount' => 50000,
        'paidAmount' => 50000,
        'status' => 'active',
    ]);

    $response = $this->getJson("/api/v1/events/{$event->id}/invitees/stats");

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.active', 1)
        ->assertJsonPath('data.confirmed', 1)
        ->assertJsonPath('data.totalPledged', 150000)
        ->assertJsonPath('data.totalPaid', 100000);
});

test('it validates phone requirement (requires normalPhone or whatsappPhone)', function () {
    [$user, $owner] = createTestInviteeUser();
    Sanctum::actingAs($user);

    $event = createTestEventForInvitees($owner->id);

    $response = $this->postJson('/api/v1/invitees', [
        'eventId' => $event->id,
        'fullName' => 'No Phone Guest',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['normalPhone', 'whatsappPhone']);
});
