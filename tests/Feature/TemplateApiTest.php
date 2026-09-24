<?php

use App\Enums\TemplateElementType;
use App\Enums\TemplateRenderMode;
use App\Enums\TemplateType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\EventOwner;
use App\Models\SystemUser;
use App\Models\Template;
use App\Models\TemplateElement;
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

function createTestAdmin(): User
{
    $adminProfile = SystemUser::create([
        'firstName' => 'Admin',
        'lastName' => 'Super',
        'email' => 'admin@iventpro.com',
        'phone' => '+255700'.rand(100000, 999999),
    ]);

    return User::create([
        'name' => 'Admin Super',
        'username' => 'admin_'.uniqid(),
        'email' => 'admin_'.uniqid().'@iventpro.com',
        'phone' => $adminProfile->phone,
        'password' => Hash::make('Secret123'),
        'role' => UserRole::Admin,
        'roleId' => $adminProfile->id,
        'status' => UserStatus::Active,
    ]);
}

function createSampleTemplate(array $attributes = []): Template
{
    return Template::create(array_merge([
        'name' => 'Elegant Gold Wedding Card',
        'pathUrl' => 'templates/backgrounds/gold-card.png',
        'previewImageUrl' => 'templates/previews/gold-card-preview.png',
        'price' => 20.00,
        'isPremium' => true,
        'category' => 'wedding',
        'templateType' => TemplateType::InvitationCard,
        'canvasWidth' => 1080,
        'canvasHeight' => 1920,
        'renderMode' => TemplateRenderMode::QrAndName,
        'isActive' => true,
    ], $attributes));
}

/*
|--------------------------------------------------------------------------
| Template Listing & Discovery Tests
|--------------------------------------------------------------------------
*/

test('it lists templates publicly with pagination and filters', function () {
    createSampleTemplate(['name' => 'Floral Wedding 1', 'category' => 'wedding', 'isPremium' => true]);
    createSampleTemplate(['name' => 'Corporate Gala Pass', 'category' => 'corporate', 'isPremium' => false, 'templateType' => TemplateType::TicketPass]);

    // Public request
    $response = $this->getJson('/api/v1/templates');
    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'templates',
                'meta' => ['currentPage', 'perPage', 'total', 'lastPage', 'hasMorePages'],
            ],
        ]);

    expect(count($response->json('data.templates')))->toBe(2);

    // Filter by category
    $filtered = $this->getJson('/api/v1/templates?category=corporate');
    $filtered->assertStatus(200);
    expect(count($filtered->json('data.templates')))->toBe(1);
    expect($filtered->json('data.templates.0.category'))->toBe('corporate');

    // Filter by isPremium
    $freeTemplates = $this->getJson('/api/v1/templates?isPremium=false');
    $freeTemplates->assertStatus(200);
    expect(count($freeTemplates->json('data.templates')))->toBe(1);
    expect($freeTemplates->json('data.templates.0.name'))->toBe('Corporate Gala Pass');
});

test('it retrieves a single template with element position shortcuts', function () {
    $template = createSampleTemplate();

    TemplateElement::create([
        'templateId' => $template->id,
        'elementType' => TemplateElementType::QrCode,
        'positionX' => 415,
        'positionY' => 1200,
        'width' => 250,
        'height' => 250,
    ]);

    TemplateElement::create([
        'templateId' => $template->id,
        'elementType' => TemplateElementType::InviteeName,
        'positionX' => 540,
        'positionY' => 1500,
        'fontSize' => 42,
        'fontColor' => '#D4AF37',
        'fontFamily' => 'Outfit',
        'alignment' => 'center',
    ]);

    $response = $this->getJson("/api/v1/templates/{$template->id}");

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Elegant Gold Wedding Card')
        ->assertJsonPath('data.qrPosition.positionX', 415)
        ->assertJsonPath('data.qrPosition.positionY', 1200)
        ->assertJsonPath('data.qrPosition.width', 250)
        ->assertJsonPath('data.namePosition.positionX', 540)
        ->assertJsonPath('data.namePosition.positionY', 1500)
        ->assertJsonPath('data.namePosition.fontColor', '#D4AF37');

    expect(count($response->json('data.elements')))->toBe(2);
});

test('it returns 404 for non-existent template', function () {
    $response = $this->getJson('/api/v1/templates/99999');
    $response->assertStatus(404)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Template not found.');
});

/*
|--------------------------------------------------------------------------
| Template Creation Tests with Positions & File Uploads
|--------------------------------------------------------------------------
*/

test('it creates a template with background file upload and element positions', function () {
    $admin = createTestAdmin();
    Sanctum::actingAs($admin);

    $file = UploadedFile::fake()->image('card-background.png', 1080, 1920);
    $previewFile = UploadedFile::fake()->image('card-preview.png', 300, 500);

    $payload = [
        'name' => 'Royal Emerald Birthday Card',
        'category' => 'birthday',
        'price' => 12.50,
        'isPremium' => true,
        'templateType' => 'invitation_card',
        'canvasWidth' => 1080,
        'canvasHeight' => 1920,
        'renderMode' => 'qr_and_name',
        'isActive' => true,
        'elements' => [
            [
                'elementType' => 'qr_code',
                'positionX' => 440,
                'positionY' => 1100,
                'width' => 200,
                'height' => 200,
            ],
            [
                'elementType' => 'invitee_name',
                'positionX' => 540,
                'positionY' => 1400,
                'fontSize' => 38,
                'fontColor' => '#0F5132',
                'fontFamily' => 'Playfair Display',
                'alignment' => 'center',
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/templates', array_merge($payload, [
        'file' => $file,
        'previewFile' => $previewFile,
    ]));

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Royal Emerald Birthday Card')
        ->assertJsonPath('data.isPremium', true)
        ->assertJsonPath('data.qrPosition.positionX', 440)
        ->assertJsonPath('data.qrPosition.positionY', 1100)
        ->assertJsonPath('data.namePosition.positionX', 540)
        ->assertJsonPath('data.namePosition.fontColor', '#0F5132');

    $templateId = $response->json('data.id');
    $this->assertDatabaseHas('templates', [
        'id' => $templateId,
        'name' => 'Royal Emerald Birthday Card',
        'price' => 12.50,
    ]);

    $this->assertDatabaseHas('template_elements', [
        'templateId' => $templateId,
        'elementType' => 'qr_code',
        'positionX' => 440,
        'positionY' => 1100,
    ]);

    $this->assertDatabaseHas('template_elements', [
        'templateId' => $templateId,
        'elementType' => 'invitee_name',
        'positionX' => 540,
        'positionY' => 1400,
    ]);
});

test('it creates a template with direct qrCode and inviteeName shortcuts', function () {
    $admin = createTestAdmin();
    Sanctum::actingAs($admin);

    $payload = [
        'name' => 'Minimalist VIP Ticket Pass',
        'pathUrl' => 'https://cdn.example.com/tickets/vip-pass.png',
        'templateType' => 'ticket_pass',
        'renderMode' => 'qr_and_name',
        'qrCode' => [
            'positionX' => 800,
            'positionY' => 300,
            'width' => 180,
            'height' => 180,
        ],
        'inviteeName' => [
            'positionX' => 200,
            'positionY' => 350,
            'fontSize' => 32,
            'fontColor' => '#111827',
            'alignment' => 'left',
        ],
    ];

    $response = $this->postJson('/api/v1/templates', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Minimalist VIP Ticket Pass')
        ->assertJsonPath('data.qrPosition.positionX', 800)
        ->assertJsonPath('data.namePosition.positionX', 200)
        ->assertJsonPath('data.namePosition.alignment', 'left');
});

/*
|--------------------------------------------------------------------------
| Template Updating & Positions Syncing Tests
|--------------------------------------------------------------------------
*/

test('it updates template attributes and elements', function () {
    $admin = createTestAdmin();
    Sanctum::actingAs($admin);

    $template = createSampleTemplate();

    $response = $this->putJson("/api/v1/templates/{$template->id}", [
        'name' => 'Updated Luxury Wedding Card',
        'price' => 30.00,
        'elements' => [
            [
                'elementType' => 'qr_code',
                'positionX' => 500,
                'positionY' => 1250,
                'width' => 220,
                'height' => 220,
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Updated Luxury Wedding Card')
        ->assertJsonPath('data.price', 30)
        ->assertJsonPath('data.qrPosition.positionX', 500)
        ->assertJsonPath('data.qrPosition.positionY', 1250);

    $this->assertDatabaseHas('templates', [
        'id' => $template->id,
        'name' => 'Updated Luxury Wedding Card',
        'price' => 30.00,
    ]);
});

test('it syncs positions for QR Code and Invitee Name via PUT /templates/{id}/positions', function () {
    $admin = createTestAdmin();
    Sanctum::actingAs($admin);

    $template = createSampleTemplate();

    $payload = [
        'qrCode' => [
            'positionX' => 450,
            'positionY' => 1050,
            'width' => 280,
            'height' => 280,
        ],
        'inviteeName' => [
            'positionX' => 540,
            'positionY' => 1420,
            'fontSize' => 40,
            'fontColor' => '#B8860B',
            'alignment' => 'center',
        ],
    ];

    $response = $this->putJson("/api/v1/templates/{$template->id}/positions", $payload);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.qrPosition.positionX', 450)
        ->assertJsonPath('data.qrPosition.positionY', 1050)
        ->assertJsonPath('data.qrPosition.width', 280)
        ->assertJsonPath('data.namePosition.positionX', 540)
        ->assertJsonPath('data.namePosition.positionY', 1420)
        ->assertJsonPath('data.namePosition.fontColor', '#B8860B');

    $this->assertDatabaseHas('template_elements', [
        'templateId' => $template->id,
        'elementType' => 'qr_code',
        'positionX' => 450,
        'positionY' => 1050,
        'width' => 280,
    ]);

    $this->assertDatabaseHas('template_elements', [
        'templateId' => $template->id,
        'elementType' => 'invitee_name',
        'positionX' => 540,
        'positionY' => 1420,
        'fontColor' => '#B8860B',
    ]);
});

test('it uploads dedicated template background and preview files', function () {
    $admin = createTestAdmin();
    Sanctum::actingAs($admin);

    $template = createSampleTemplate();

    $newBackground = UploadedFile::fake()->image('new-bg.png', 1080, 1920);

    $response = $this->postJson("/api/v1/templates/{$template->id}/file", [
        'file' => $newBackground,
        'type' => 'template',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['data' => ['url', 'type']]);

    $template->refresh();
    expect($template->pathUrl)->not->toBe('templates/backgrounds/gold-card.png');
});

/*
|--------------------------------------------------------------------------
| Template Deletion Tests
|--------------------------------------------------------------------------
*/

test('it prevents deleting template when assigned to active events', function () {
    $admin = createTestAdmin();
    Sanctum::actingAs($admin);

    $template = createSampleTemplate();

    $owner = EventOwner::create([
        'firstName' => 'John',
        'lastName' => 'Doe',
        'email' => 'owner_'.uniqid().'@iventpro.com',
        'phone' => '+255711'.rand(100000, 999999),
    ]);

    Event::create([
        'title' => 'Wedding Event Using Template',
        'slug' => 'wedding-event-'.uniqid(),
        'eventCategory' => 'normal',
        'eventOwnerId' => $owner->id,
        'templateId' => $template->id,
        'status' => 'published',
    ]);

    $response = $this->deleteJson("/api/v1/templates/{$template->id}");

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonValidationErrors(['template']);

    $this->assertDatabaseHas('templates', ['id' => $template->id]);
});

test('it successfully deletes an unused template and cascades elements', function () {
    $admin = createTestAdmin();
    Sanctum::actingAs($admin);

    $template = createSampleTemplate();
    $element = TemplateElement::create([
        'templateId' => $template->id,
        'elementType' => TemplateElementType::QrCode,
        'positionX' => 100,
        'positionY' => 100,
    ]);

    $response = $this->deleteJson("/api/v1/templates/{$template->id}");

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Template deleted successfully.');

    $this->assertDatabaseMissing('templates', ['id' => $template->id]);
    $this->assertDatabaseMissing('template_elements', ['id' => $element->id]);
});

test('it forbids unauthenticated users from modifying templates', function () {
    $template = createSampleTemplate();

    $this->postJson('/api/v1/templates', ['name' => 'Hack Card'])->assertStatus(401);
    $this->putJson("/api/v1/templates/{$template->id}", ['name' => 'Hack Card'])->assertStatus(401);
    $this->deleteJson("/api/v1/templates/{$template->id}")->assertStatus(401);
    $this->putJson("/api/v1/templates/{$template->id}/positions", ['qrCode' => ['positionX' => 100, 'positionY' => 100]])->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Additional Edge Case & Validation Scenarios
|--------------------------------------------------------------------------
*/

test('it validates required fields when creating a template without file or pathUrl', function () {
    $admin = createTestAdmin();
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/templates', [
        'name' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'pathUrl']);
});

test('it searches templates by text query across name and category', function () {
    createSampleTemplate(['name' => 'Imperial Safari Invitation', 'category' => 'safari']);
    createSampleTemplate(['name' => 'Sunset Beach Party', 'category' => 'beach']);

    $response = $this->getJson('/api/v1/templates?query=Safari');
    $response->assertStatus(200);
    expect(count($response->json('data.templates')))->toBe(1);
    expect($response->json('data.templates.0.name'))->toBe('Imperial Safari Invitation');

    $categorySearch = $this->getJson('/api/v1/templates?query=beach');
    $categorySearch->assertStatus(200);
    expect(count($categorySearch->json('data.templates')))->toBe(1);
    expect($categorySearch->json('data.templates.0.category'))->toBe('beach');
});

test('it uploads dedicated preview thumbnail file', function () {
    $admin = createTestAdmin();
    Sanctum::actingAs($admin);

    $template = createSampleTemplate();
    $previewFile = UploadedFile::fake()->image('thumb.webp', 300, 500);

    $response = $this->postJson("/api/v1/templates/{$template->id}/file", [
        'file' => $previewFile,
        'type' => 'preview',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.type', 'preview');

    $template->refresh();
    expect($template->previewImageUrl)->not->toBe('templates/previews/gold-card-preview.png');
});

test('it allows force deleting a template even if events are attached', function () {
    $admin = createTestAdmin();
    Sanctum::actingAs($admin);

    $template = createSampleTemplate();

    $owner = EventOwner::create([
        'firstName' => 'Grace',
        'lastName' => 'Kimaro',
        'email' => 'grace_'.uniqid().'@iventpro.com',
        'phone' => '+255711'.rand(100000, 999999),
    ]);

    Event::create([
        'title' => 'Event With Forced Template Delete',
        'slug' => 'event-force-'.uniqid(),
        'eventCategory' => 'normal',
        'eventOwnerId' => $owner->id,
        'templateId' => $template->id,
        'status' => 'draft',
    ]);

    $response = $this->deleteJson("/api/v1/templates/{$template->id}?force=true");

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseMissing('templates', ['id' => $template->id]);
});

test('it rejects invalid element types when syncing positions', function () {
    $admin = createTestAdmin();
    Sanctum::actingAs($admin);

    $template = createSampleTemplate();

    $response = $this->putJson("/api/v1/templates/{$template->id}/positions", [
        'elements' => [
            [
                'elementType' => 'invalid_type_xyz',
                'positionX' => 100,
                'positionY' => 100,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['elements.0.elementType']);
});

