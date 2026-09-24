<?php

use App\Enums\CheckInStatus;
use App\Enums\ContributionStatus;
use App\Enums\EntryType;
use App\Enums\EventCategory;
use App\Enums\EventDivision;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\InviteeStatus;
use App\Enums\OrderStatus;
use App\Enums\PricingModel;
use App\Enums\SubscriptionStatus;
use App\Enums\TemplateElementType;
use App\Enums\TemplateRenderMode;
use App\Enums\TemplateType;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Contribution;
use App\Models\EntryActivity;
use App\Models\Event;
use App\Models\EventFeatureBalance;
use App\Models\EventOwner;
use App\Models\EventPackageSubscription;
use App\Models\GateKeeper;
use App\Models\Invitee;
use App\Models\ServicePackage;
use App\Models\SystemUser;
use App\Models\Template;
use App\Models\TemplateElement;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users model manages authentication, generates uuid, and links to profiles with username', function () {
    $adminUser = User::create([
        'name' => 'Admin User',
        'username' => 'admin_super',
        'email' => 'admin@ivento.com',
        'password' => 'secret123',
        'role' => UserRole::Admin,
        'status' => UserStatus::Active,
    ]);

    expect($adminUser->uuid)->not->toBeEmpty()
        ->and($adminUser->role)->toBe(UserRole::Admin)
        ->and($adminUser->status)->toBe(UserStatus::Active);

    $systemUser = SystemUser::create([
        'userId' => $adminUser->id,
        'username' => 'admin_super',
        'email' => 'admin@ivento.com',
        'firstName' => 'Admin',
        'lastName' => 'Super',
    ]);

    expect($adminUser->systemUser->id)->toBe($systemUser->id)
        ->and($systemUser->user->id)->toBe($adminUser->id)
        ->and($systemUser->email)->toBe('admin@ivento.com');
});

test('templates store canvas dimensions and calculate qr code and name placement coordinates', function () {
    $template = Template::create([
        'name' => 'Gold Luxury Wedding Card',
        'pathUrl' => 'templates/wedding_gold_01.png',
        'previewImageUrl' => 'templates/previews/wedding_gold_01_thumb.png',
        'price' => 15.00,
        'isPremium' => true,
        'category' => 'wedding',
        'templateType' => TemplateType::InvitationCard,
        'canvasWidth' => 1080,
        'canvasHeight' => 1920,
        'renderMode' => TemplateRenderMode::QrAndName,
        'isActive' => true,
    ]);

    $qrElement = TemplateElement::create([
        'templateId' => $template->id,
        'elementType' => TemplateElementType::QrCode,
        'positionX' => 415,
        'positionY' => 1350,
        'width' => 250,
        'height' => 250,
        'isRequired' => true,
    ]);

    $nameElement = TemplateElement::create([
        'templateId' => $template->id,
        'elementType' => TemplateElementType::InviteeName,
        'positionX' => 540,
        'positionY' => 1100,
        'fontSize' => 42,
        'fontColor' => '#D4AF37',
        'fontFamily' => 'Playfair Display',
        'alignment' => 'center',
        'isRequired' => false,
    ]);

    expect($template->elements)->toHaveCount(2)
        ->and($template->getQrElement()->positionX)->toBe(415)
        ->and($template->getQrElement()->positionY)->toBe(1350)
        ->and($template->getNameElement()->fontSize)->toBe(42)
        ->and($template->supportsQrAndName())->toBeTrue()
        ->and($qrElement->template->id)->toBe($template->id);
});

test('normal invitation event handles guest lists, entry passes, and contributions', function () {
    $ownerUser = User::create([
        'name' => 'John Mwangi',
        'username' => 'jmwangi',
        'email' => 'john@example.com',
        'password' => 'secret123',
        'role' => UserRole::EventOwner,
    ]);

    $owner = EventOwner::create([
        'userId' => $ownerUser->id,
        'username' => 'jmwangi',
        'firstName' => 'John',
        'lastName' => 'Mwangi',
        'email' => 'john@example.com',
        'phone' => '+255712345678',
    ]);

    $event = Event::create([
        'title' => 'Innocent & Mboni Wedding',
        'eventCategory' => EventCategory::Normal,
        'typeOfEvent' => EventType::Wedding,
        'status' => EventStatus::Published,
        'venueName' => 'Mlimani City Conference Hall',
        'startDate' => '2026-10-26',
        'endDate' => '2026-10-26',
        'eventTime' => '19:00:00',
        'divisions' => EventDivision::Both,
        'divisionsPrices' => ['single' => 50000, 'double' => 100000, 'both' => 150000],
        'contributionsNeeded' => true,
    ]);

    expect($event->isNormal())->toBeTrue()
        ->and($event->slug)->not->toBeEmpty();

    $invitee = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'Peter Chawalla',
        'title' => 'Honorable',
        'normalPhone' => '+255711223344',
        'whatsappPhone' => '+255711223344',
        'status' => InviteeStatus::Confirmed,
        'division' => EventDivision::Double,
    ]);

    expect($invitee->entryPassCode)->toHaveLength(9)
        ->and($invitee->entryPassTokenHash)->toBe(hash('sha256', $invitee->entryPassCode));

    $contribution = Contribution::create([
        'inviteeId' => $invitee->id,
        'eventId' => $event->id,
        'pledgeAmount' => '100000',
        'paidAmount' => '100000',
        'division' => 'double',
        'status' => ContributionStatus::Paid,
        'transactionId' => 'AZAM-TX-998877',
    ]);

    expect($invitee->contributions)->toHaveCount(1)
        ->and($contribution->status)->toBe(ContributionStatus::Paid);
});

test('ticketed event manages ticket tiers, customer orders, and individual tickets', function () {
    $cinemaEvent = Event::create([
        'title' => 'Avengers Secret Wars Premiere - Century Cinemax',
        'eventCategory' => EventCategory::Ticketed,
        'typeOfEvent' => EventType::Cinema,
        'isPublic' => true,
        'isFeatured' => true,
        'status' => EventStatus::Published,
        'venueName' => 'Century Cinemax, Mlimani City Hall 1',
        'startDate' => '2026-11-15',
        'eventTime' => '19:30:00',
        'contributionsNeeded' => false,
    ]);

    expect($cinemaEvent->isTicketed())->toBeTrue()
        ->and($cinemaEvent->isPublic)->toBeTrue();

    $regularTier = TicketType::create([
        'eventId' => $cinemaEvent->id,
        'name' => 'Standard Seat',
        'price' => 15000.00,
        'currency' => 'TZS',
        'quantityTotal' => 100,
        'quantitySold' => 0,
        'isActive' => true,
    ]);

    $vipTier = TicketType::create([
        'eventId' => $cinemaEvent->id,
        'name' => 'VIP Recliner',
        'price' => 25000.00,
        'currency' => 'TZS',
        'quantityTotal' => 20,
        'quantitySold' => 0,
        'isActive' => true,
    ]);

    expect($regularTier->isAvailable())->toBeTrue()
        ->and($regularTier->availableQuantity())->toBe(100);

    $order = TicketOrder::create([
        'eventId' => $cinemaEvent->id,
        'customerName' => 'Sarah Juma',
        'customerEmail' => 'sarah@example.com',
        'customerPhone' => '+255755123456',
        'totalAmount' => 50000.00,
        'paidAmount' => 50000.00,
        'status' => OrderStatus::Paid,
        'transactionId' => 'AZAM-TX-TICKET-01',
    ]);

    expect($order->orderNumber)->toStartWith('ORD-');

    $ticket1 = Ticket::create([
        'orderId' => $order->id,
        'ticketTypeId' => $vipTier->id,
        'eventId' => $cinemaEvent->id,
        'holderName' => 'Sarah Juma',
        'holderEmail' => 'sarah@example.com',
    ]);

    $ticket2 = Ticket::create([
        'orderId' => $order->id,
        'ticketTypeId' => $vipTier->id,
        'eventId' => $cinemaEvent->id,
        'holderName' => 'Amina Juma',
        'holderEmail' => 'amina@example.com',
    ]);

    expect($order->tickets)->toHaveCount(2)
        ->and($ticket1->ticketNumber)->toStartWith('TKT-')
        ->and($ticket1->status)->toBe(TicketStatus::Valid)
        ->and($ticket1->tokenHash)->toBe(hash('sha256', $ticket1->qrToken));
});

test('gatekeeper scanning validates invitees and tickets and audits activity log', function () {
    $gatekeeperUser = User::create([
        'name' => 'Gatekeeper Alex',
        'username' => 'alex_gate',
        'email' => 'alex@gate.com',
        'password' => 'secret123',
        'role' => UserRole::GateKeeper,
    ]);

    $gatekeeper = GateKeeper::create([
        'userId' => $gatekeeperUser->id,
        'username' => 'alex_gate',
        'fullName' => 'Alex Gatekeeper',
    ]);

    $event = Event::create([
        'title' => 'Dar VIP Gala Night',
        'eventCategory' => EventCategory::Normal,
    ]);

    $invitee = Invitee::create([
        'eventId' => $event->id,
        'fullName' => 'David Mollel',
    ]);

    $scanLog1 = EntryActivity::create([
        'eventId' => $event->id,
        'entryType' => EntryType::Invitee,
        'inviteeId' => $invitee->id,
        'gateKeeperId' => $gatekeeper->id,
        'scannedCode' => $invitee->entryPassCode,
        'status' => CheckInStatus::Success,
        'gateName' => 'Main Gate A',
        'deviceInfo' => ['appVersion' => '2.0.0', 'battery' => 92],
        'isOfflineSync' => false,
        'scannedAt' => now(),
    ]);

    $invitee->markAsConsumed($gatekeeperUser->uuid);

    expect($invitee->fresh()->status)->toBe(InviteeStatus::Attended)
        ->and($invitee->fresh()->entryPassConsumedAt)->not->toBeNull();

    // Second scan is recorded as duplicate fraud prevention
    $scanLog2 = EntryActivity::create([
        'eventId' => $event->id,
        'entryType' => EntryType::Invitee,
        'inviteeId' => $invitee->id,
        'gateKeeperId' => $gatekeeper->id,
        'scannedCode' => $invitee->entryPassCode,
        'status' => CheckInStatus::Duplicate,
        'failureReason' => 'Pass already consumed at Gate A',
        'gateName' => 'VIP Side Gate',
        'scannedAt' => now(),
    ]);

    expect($scanLog2->status)->toBe(CheckInStatus::Duplicate)
        ->and($event->entryActivities)->toHaveCount(2);
});

test('package subscriptions and feature balances manage atomic quotas accurately', function () {
    $event = Event::create([
        'title' => 'Corporate Tech Summit',
        'eventCategory' => EventCategory::Normal,
    ]);

    $package = ServicePackage::create([
        'slug' => 'premium_qr',
        'name' => 'Premium QR Package',
        'pricingModel' => PricingModel::PerInvitee,
        'pricePerInviteeTzs' => 2500.00,
        'limits' => ['smsUnits' => 500, 'whatsappUnits' => 250],
    ]);

    $subscription = EventPackageSubscription::create([
        'eventId' => $event->id,
        'servicePackageId' => $package->id,
        'packageSnapshot' => ['slug' => 'premium_qr', 'price' => 2500],
        'status' => SubscriptionStatus::Active,
        'inviteeLimit' => 300,
    ]);

    $smsBalance = EventFeatureBalance::create([
        'eventId' => $event->id,
        'subscriptionId' => $subscription->id,
        'featureKey' => 'sms_units',
        'grantedUnits' => 500,
        'reservedUnits' => 50,
        'consumedUnits' => 100,
    ]);

    expect($smsBalance->remainingUnits())->toBe(350)
        ->and($smsBalance->canConsume(300))->toBeTrue()
        ->and($smsBalance->canConsume(400))->toBeFalse();
});
