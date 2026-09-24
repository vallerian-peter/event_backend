<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommitteeController;
use App\Http\Controllers\Api\V1\ContributionController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\EventOwnerPreferenceController;
use App\Http\Controllers\Api\V1\GateKeeperController;
use App\Http\Controllers\Api\V1\InviteeController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProjectBriefController;
use App\Http\Controllers\Api\V1\PublicEventController;
use App\Http\Controllers\Api\V1\ServicePackageController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\TicketingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function (): void {
    // Authentication Endpoints
    Route::prefix('auth')->group(function (): void {
        // Public Endpoints
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        // Authenticated Endpoints
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    // Public Events Catalog & Discovery
    Route::prefix('public')->group(function (): void {
        Route::get('/events', [PublicEventController::class, 'index']);
        Route::get('/events/{slug}', [PublicEventController::class, 'show']);
        Route::post('/events/{eventId}/tickets/checkout', [TicketingController::class, 'checkout']);
        Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:10,1');
        Route::post('/inquiries/project-brief', [ProjectBriefController::class, 'store'])->middleware('throttle:5,1');
    });

    // Public Inquiries & Newsletter Subscriptions (Direct alias)
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:10,1');
    Route::post('/inquiries/project-brief', [ProjectBriefController::class, 'store'])->middleware('throttle:5,1');

    // Inquiries Management (Authenticated Admins/Concierge)
    Route::middleware('auth:sanctum')->prefix('inquiries')->group(function (): void {
        Route::get('/project-briefs', [ProjectBriefController::class, 'index']);
        Route::get('/project-briefs/{id}', [ProjectBriefController::class, 'show']);
        Route::patch('/project-briefs/{id}/status', [ProjectBriefController::class, 'updateStatus']);
    });

    // Public / Gatekeeper Ticket Verification
    Route::get('/tickets/{code}/verify', [TicketingController::class, 'verify']);
    Route::get('/ticket-orders/{orderNumber}', [TicketingController::class, 'showOrder']);

    // Events Endpoints
    Route::prefix('events')->group(function (): void {
        // Public Discovery (legacy alias)
        Route::get('/public', [EventController::class, 'publicEvents']);

        // Authenticated Event CRUD & Management
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/', [EventController::class, 'index']);
            Route::post('/', [EventController::class, 'store']);
            Route::match(['put', 'patch'], '/{id}', [EventController::class, 'update']);
            Route::delete('/{id}', [EventController::class, 'destroy']);
            Route::post('/{id}/flyer', [EventController::class, 'uploadFlyer']);
            Route::patch('/{id}/status', [EventController::class, 'updateStatus']);

            // Event Committee Management
            Route::get('/{eventId}/committee/members', [CommitteeController::class, 'indexMembers']);
            Route::post('/{eventId}/committee/members', [CommitteeController::class, 'storeMember']);
            Route::match(['put', 'patch'], '/{eventId}/committee/members/{memberId}', [CommitteeController::class, 'updateMember']);
            Route::delete('/{eventId}/committee/members/{memberId}', [CommitteeController::class, 'destroyMember']);
            Route::get('/{eventId}/committee/meetings', [CommitteeController::class, 'indexMeetings']);
            Route::post('/{eventId}/committee/meetings', [CommitteeController::class, 'storeMeeting']);
            Route::get('/{eventId}/committee/meetings/{meetingId}', [CommitteeController::class, 'showMeeting']);
            Route::post('/{eventId}/committee/meetings/{meetingId}/notes', [CommitteeController::class, 'storeNote']);
            Route::post('/{eventId}/committee/meetings/{meetingId}/remind', [CommitteeController::class, 'sendMeetingReminders']);

            // Gatekeeper assignments & live activities
            Route::get('/{eventId}/gate-keepers', [GateKeeperController::class, 'index']);
            Route::post('/{eventId}/gate-keepers', [GateKeeperController::class, 'store']);
            Route::get('/{eventId}/gate/activities', [GateKeeperController::class, 'activities']);

            // Ticket Types & Orders Management
            Route::get('/{eventId}/ticket-types', [TicketingController::class, 'indexTypes']);
            Route::post('/{eventId}/ticket-types', [TicketingController::class, 'storeType']);
            Route::get('/{eventId}/ticket-orders', [TicketingController::class, 'indexOrders']);

            // Package Subscriptions
            Route::post('/{eventId}/subscribe-package', [ServicePackageController::class, 'subscribe']);
            Route::get('/{eventId}/subscription', [ServicePackageController::class, 'subscription']);
        });

        // View single event (supports public events and authenticated private events)
        Route::get('/{idOrSlug}', [EventController::class, 'show']);
    });

    // Gatekeepers Auth & Mobile Manifest
    Route::post('/gate-keepers/auth', [GateKeeperController::class, 'authenticate']);
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/gate-keepers/events/{eventId}/manifest', [GateKeeperController::class, 'manifest']);
        Route::post('/tickets/{code}/check-in', [TicketingController::class, 'checkIn']);
    });

    // Ticket Types Standalone Routes
    Route::prefix('ticket-types')->group(function (): void {
        Route::get('/{id}', [TicketingController::class, 'showType']);
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::match(['put', 'patch'], '/{id}', [TicketingController::class, 'updateType']);
            Route::delete('/{id}', [TicketingController::class, 'destroyType']);
        });
    });

    // Service Packages Catalog
    Route::prefix('service-packages')->group(function (): void {
        Route::get('/', [ServicePackageController::class, 'index']);
        Route::get('/{idOrSlug}', [ServicePackageController::class, 'show']);
    });

    // Event Owner Preferences
    Route::prefix('event-owners')->group(function (): void {
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/preferences', [EventOwnerPreferenceController::class, 'show']);
            Route::match(['put', 'patch'], '/preferences', [EventOwnerPreferenceController::class, 'update']);
        });
    });

    // Templates Endpoints
    Route::prefix('templates')->group(function (): void {
        // Public Discovery
        Route::get('/', [TemplateController::class, 'index']);
        Route::get('/{id}', [TemplateController::class, 'show']);

        // Authenticated Template Management & Positions Handling
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/', [TemplateController::class, 'store']);
            Route::match(['put', 'patch'], '/{id}', [TemplateController::class, 'update']);
            Route::delete('/{id}', [TemplateController::class, 'destroy']);
            Route::put('/{id}/positions', [TemplateController::class, 'syncPositions']);
            Route::post('/{id}/file', [TemplateController::class, 'uploadFile']);
        });
    });

    // Invitees Endpoints
    Route::prefix('invitees')->group(function (): void {
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/', [InviteeController::class, 'index']);
            Route::post('/', [InviteeController::class, 'store']);
            Route::post('/bulk', [InviteeController::class, 'bulkStore']);
            Route::patch('/bulk-status', [InviteeController::class, 'bulkStatus']);
            Route::get('/{id}', [InviteeController::class, 'show']);
            Route::match(['put', 'patch'], '/{id}', [InviteeController::class, 'update']);
            Route::delete('/{id}', [InviteeController::class, 'destroy']);
            Route::get('/{id}/qr-preview', [InviteeController::class, 'qrPreview']);
            Route::post('/{id}/check-in', [InviteeController::class, 'checkIn']);
        });
    });

    // Event Nested Invitee Stats & Listing
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/events/{eventId}/invitees', [InviteeController::class, 'index']);
        Route::get('/events/{eventId}/invitees/stats', [InviteeController::class, 'stats']);
    });

    // Contributions Endpoints
    Route::prefix('contributions')->group(function (): void {
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/', [ContributionController::class, 'index']);
            Route::post('/', [ContributionController::class, 'store']);
            Route::get('/{id}', [ContributionController::class, 'show']);
            Route::match(['put', 'patch'], '/{id}', [ContributionController::class, 'update']);
            Route::delete('/{id}', [ContributionController::class, 'destroy']);
        });
    });

    // Payments & Checkout Endpoints
    Route::prefix('payments')->group(function (): void {
        // Inbound Webhooks / Callbacks (Public, signature-verified)
        Route::post('/webhook', [PaymentController::class, 'webhook']);
        Route::post('/callback', [PaymentController::class, 'webhook']);
        Route::get('/{identifier}/status', [PaymentController::class, 'status']);

        // Authenticated Checkout
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/checkout', [PaymentController::class, 'checkout']);
        });
    });

    // Messaging & Broadcasting Endpoints
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/events/{eventId}/messages/send', [MessageController::class, 'send']);
        Route::get('/events/{eventId}/messages', [MessageController::class, 'index']);
        Route::get('/events/{eventId}/messages/records', [MessageController::class, 'records']);
        Route::get('/events/{eventId}/features/balance', [MessageController::class, 'featureBalance']);
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
