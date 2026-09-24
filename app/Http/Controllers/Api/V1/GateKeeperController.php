<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GateKeeper\CreateGateKeeperRequest;
use App\Http\Requests\GateKeeper\GateKeeperAuthRequest;
use App\Services\GateKeeperService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GateKeeperController extends Controller
{
    public function __construct(
        protected GateKeeperService $gateKeeperService
    ) {}

    /**
     * List gatekeepers assigned to an event.
     *
     * GET /api/v1/events/{eventId}/gate-keepers
     */
    public function index(Request $request, int $eventId): JsonResponse
    {
        try {
            $gateKeepers = $this->gateKeeperService->listGateKeepers($eventId, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Gatekeepers retrieved successfully.',
                'data' => $gateKeepers,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Create a new gatekeeper and issue access PIN credentials.
     *
     * POST /api/v1/events/{eventId}/gate-keepers
     */
    public function store(CreateGateKeeperRequest $request, int $eventId): JsonResponse
    {
        try {
            $result = $this->gateKeeperService->createGateKeeper($eventId, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Gatekeeper created successfully.',
                'data' => $result,
            ], 201);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Authenticate gatekeeper via identifier and PIN.
     *
     * POST /api/v1/gate-keepers/auth
     */
    public function authenticate(GateKeeperAuthRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $authResult = $this->gateKeeperService->authenticate($data['identifier'], $data['pin']);

            return response()->json([
                'status' => 'success',
                'message' => 'Gatekeeper authenticated successfully.',
                'data' => $authResult,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get attendee manifest for offline sync or scanning.
     *
     * GET /api/v1/gate-keepers/events/{eventId}/manifest
     */
    public function manifest(Request $request, int $eventId): JsonResponse
    {
        try {
            $search = $request->query('search');
            $manifest = $this->gateKeeperService->getEventManifest($eventId, is_string($search) ? $search : null);

            return response()->json([
                'status' => 'success',
                'message' => 'Attendee manifest retrieved successfully.',
                'data' => $manifest,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        }
    }

    /**
     * Get live entry activities / scan feed for an event.
     *
     * GET /api/v1/events/{eventId}/gate/activities
     */
    public function activities(Request $request, int $eventId): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 50);
            $activities = $this->gateKeeperService->getGateActivities($eventId, $request->user(), $perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Gate activities retrieved successfully.',
                'data' => $activities,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }
}
