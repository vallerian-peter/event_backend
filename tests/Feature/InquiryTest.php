<?php

use App\Models\NewsletterSubscriber;
use App\Models\ProjectBrief;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('a user can subscribe to the newsletter successfully', function () {
    $payload = [
        'email' => 'subscriber@example.com',
        'source' => 'footer',
    ];

    $response = $this->postJson('/api/v1/public/newsletter/subscribe', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.email', 'subscriber@example.com')
        ->assertJsonPath('data.subscriptionStatus', 'subscribed');

    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'subscriber@example.com',
        'source' => 'footer',
        'status' => 'active',
    ]);
});

test('subscribing with an existing active email is idempotent', function () {
    NewsletterSubscriber::create([
        'email' => 'existing@example.com',
        'source' => 'footer',
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'existing@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.subscriptionStatus', 'already_subscribed');

    expect(NewsletterSubscriber::where('email', 'existing@example.com')->count())->toBe(1);
});

test('subscribing with an invalid email returns 422', function () {
    $response = $this->postJson('/api/v1/newsletter/subscribe', [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('a client can submit a start-your-project brief successfully', function () {
    $payload = [
        'name' => 'Alex Morgan',
        'email' => 'alex.morgan@company.com',
        'phone' => '+255714555666',
        'date' => '2026-12-15',
        'type' => 'Corporate Annual Summit',
        'guestCount' => '300-500',
        'venueLocation' => 'Julius Nyerere International Convention Centre',
        'requestedServices' => ['gate_pass_qr', 'sms_automation', 'rsvp_portal'],
        'message' => 'We are planning our 2026 flagship executive summit and need digital passes and SMS broadcast.',
    ];

    $response = $this->postJson('/api/v1/public/inquiries/project-brief', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.fullName', 'Alex Morgan')
        ->assertJsonPath('data.email', 'alex.morgan@company.com')
        ->assertJsonPath('data.eventType', 'Corporate Annual Summit')
        ->assertJsonPath('data.status', 'new');

    $refCode = $response->json('data.referenceCode');
    expect($refCode)->toStartWith('BRF-');

    $this->assertDatabaseHas('project_briefs', [
        'referenceCode' => $refCode,
        'fullName' => 'Alex Morgan',
        'email' => 'alex.morgan@company.com',
        'phone' => '+255714555666',
        'eventType' => 'Corporate Annual Summit',
        'estimatedGuests' => '300-500',
        'status' => 'new',
    ]);
});

test('project brief submission requires name, email, phone and message', function () {
    $response = $this->postJson('/api/v1/inquiries/project-brief', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'phone', 'message', 'name']);
});

test('authenticated staff can list, view, and update project briefs', function () {
    $staff = User::factory()->create([
        'email' => 'concierge@iventpro.com',
    ]);

    $brief = ProjectBrief::create([
        'referenceCode' => 'BRF-TEST01',
        'fullName' => 'Ester Minja',
        'email' => 'ester@gmail.com',
        'phone' => '+255719888999',
        'eventType' => 'Wedding Celebration',
        'eventDate' => '2026-11-28',
        'message' => 'Looking for complete wedding guest management and contribution tracking.',
        'status' => 'new',
    ]);

    Sanctum::actingAs($staff);

    // List
    $listResponse = $this->getJson('/api/v1/inquiries/project-briefs');
    $listResponse->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.data');

    // Show
    $showResponse = $this->getJson("/api/v1/inquiries/project-briefs/{$brief->id}");
    $showResponse->assertStatus(200)
        ->assertJsonPath('data.referenceCode', 'BRF-TEST01');

    // Update status to contacted with notes
    $patchResponse = $this->patchJson("/api/v1/inquiries/project-briefs/{$brief->id}/status", [
        'status' => 'contacted',
        'adminNotes' => 'Spoke with Ester via phone. Scheduled workspace demo for Friday 2 PM.',
        'assignedToUserId' => $staff->id,
    ]);

    $patchResponse->assertStatus(200)
        ->assertJsonPath('data.status', 'contacted')
        ->assertJsonPath('data.adminNotes', 'Spoke with Ester via phone. Scheduled workspace demo for Friday 2 PM.')
        ->assertJsonPath('data.assignedToUserId', $staff->id);

    expect($brief->fresh()->contactedAt)->not->toBeNull();
});
