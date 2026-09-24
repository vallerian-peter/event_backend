<?php

use App\Enums\EventLanguage;
use App\Enums\PricingModel;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\EventOwner;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createPackageTestOwner(): array
{
    $owner = EventOwner::create([
        'firstName' => 'Tariq',
        'lastName' => 'Simba',
        'email' => 'tariq_'.uniqid().'@iventpro.com',
        'phone' => '+255718'.rand(100000, 999999),
        'eventIds' => [],
    ]);

    $user = User::create([
        'name' => 'Tariq Simba',
        'username' => 'tariq_'.uniqid(),
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

function createPackageTestEvent(int $ownerId): Event
{
    return Event::create([
        'title' => 'Simba Corporate Summit',
        'slug' => 'simba-summit-'.uniqid(),
        'eventCategory' => 'normal',
        'eventOwnerId' => $ownerId,
        'venueName' => 'JNICC Dar es Salaam',
        'startDate' => '2026-11-28',
        'endDate' => '2026-11-28',
        'inviteeLimit' => 100,
        'status' => 'published',
    ]);
}

test('public user can list available service packages and view details by slug', function () {
    $package = ServicePackage::create([
        'slug' => 'starter-tier',
        'version' => 1,
        'name' => 'Starter Event Package',
        'description' => 'Ideal for small intimate events up to 100 invitees',
        'priceUsd' => 25.00,
        'pricingModel' => PricingModel::Fixed,
        'capabilities' => ['qr_entry', 'guest_list', 'sms_notifications'],
        'limits' => ['maxInvitees' => 100, 'includedSms' => 50],
        'isActive' => true,
    ]);

    // List all
    $listResponse = $this->getJson('/api/v1/service-packages');
    $listResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data');

    // Show by slug
    $slugResponse = $this->getJson("/api/v1/service-packages/{$package->slug}");
    $slugResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.slug', 'starter-tier')
        ->assertJsonPath('data.name', 'Starter Event Package');
});

test('event owner can subscribe event to service package and provision feature balance', function () {
    [$user, $owner] = createPackageTestOwner();
    $event = createPackageTestEvent($owner->id);

    $package = ServicePackage::create([
        'slug' => 'premium-pro',
        'version' => 1,
        'name' => 'Premium Pro Package',
        'priceUsd' => 99.00,
        'pricingModel' => PricingModel::Fixed,
        'capabilities' => ['qr_entry', 'manifest', 'bulk_sms', 'custom_branding'],
        'limits' => ['maxInvitees' => 500, 'includedSms' => 300],
        'isActive' => true,
    ]);

    Sanctum::actingAs($user);

    $subscribeResponse = $this->postJson("/api/v1/events/{$event->id}/subscribe-package", [
        'servicePackageId' => $package->id,
        'inviteeLimit' => 500,
        'smsUnits' => 300,
    ]);

    $subscribeResponse->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.subscription.status', SubscriptionStatus::Active->value)
        ->assertJsonPath('data.featureBalance.featureKey', 'sms_broadcast')
        ->assertJsonPath('data.featureBalance.grantedUnits', 300);

    // Get current subscription & balances
    $detailsResponse = $this->getJson("/api/v1/events/{$event->id}/subscription");
    $detailsResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.servicePackageSlug', 'premium-pro')
        ->assertJsonPath('data.inviteeLimit', 500)
        ->assertJsonCount(1, 'data.balances');
});

test('event owner can view and update their preferences', function () {
    [$user, $owner] = createPackageTestOwner();

    Sanctum::actingAs($user);

    // Initial get (auto-creates defaults if none exist)
    $getResponse = $this->getJson('/api/v1/event-owners/preferences');
    $getResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.eventLanguage', EventLanguage::Swahili->value);

    // Update preferences
    $updateResponse = $this->putJson('/api/v1/event-owners/preferences', [
        'eventLanguage' => EventLanguage::English->value,
        'timezone' => 'Africa/Nairobi',
        'preferredChannels' => ['whatsapp'],
        'messagePreferences' => ['includeEventLink' => false],
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.eventLanguage', EventLanguage::English->value)
        ->assertJsonPath('data.timezone', 'Africa/Nairobi');

    $this->assertDatabaseHas('event_owner_preferences', [
        'eventOwnerId' => $owner->id,
        'timezone' => 'Africa/Nairobi',
    ]);
});
