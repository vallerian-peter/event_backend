<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Event\CreateEventDTO;
use App\DTOs\Event\EventListResponseDTO;
use App\DTOs\Event\EventResponseDTO;
use App\DTOs\Event\UpdateEventDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\CreateEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Requests\Event\UploadEventFlyerRequest;
use App\Services\EventService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller handling Events CRUD API (V1) endpoints.
 */
class EventController extends Controller
{
    public function __construct(
        protected readonly EventService $eventService
    ) {}

    /**
     * List events for the authenticated user/admin with filters and pagination.
     *
     * GET /api/v1/events
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->all();
        $perPage = min(100, max(1, (int) $request->input('perPage', 15)));

        $paginator = $this->eventService->listEvents($filters, $request->user(), $perPage);
        $listDto = EventListResponseDTO::fromPaginator($paginator);

        return response()->json([
            'status' => 'success',
            'message' => 'Events retrieved successfully.',
            'data' => $listDto->toArray(),
        ]);
    }

    /**
     * Create a new event (normal or ticketed).
     *
     * POST /api/v1/events
     */
    public function store(CreateEventRequest $request): JsonResponse
    {
        $dto = CreateEventDTO::fromRequest($request);
        $flyerFile = $request->file('flyer');

        $event = $this->eventService->createEvent($dto, $flyerFile, $request->user());
        $responseDto = EventResponseDTO::fromModel($event);

        return response()->json([
            'status' => 'success',
            'message' => 'Event created successfully.',
            'data' => $responseDto->toArray(),
        ], 201);
    }

    /**
     * Retrieve single event by ID or unique slug.
     *
     * GET /api/v1/events/{idOrSlug}
     */
    public function show(Request $request, string $idOrSlug): JsonResponse
    {
        try {
            $user = $request->user('sanctum');
            $event = $this->eventService->getEvent($idOrSlug, $user);
            $responseDto = EventResponseDTO::fromModel($event);

            return response()->json([
                'status' => 'success',
                'message' => 'Event retrieved successfully.',
                'data' => $responseDto->toArray(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Update an existing event.
     *
     * PUT/PATCH /api/v1/events/{id}
     */
    public function update(UpdateEventRequest $request, int $id): JsonResponse
    {
        try {
            $dto = UpdateEventDTO::fromRequest($request);
            $flyerFile = $request->file('flyer');

            $event = $this->eventService->updateEvent($id, $dto, $flyerFile, $request->user());
            $responseDto = EventResponseDTO::fromModel($event);

            return response()->json([
                'status' => 'success',
                'message' => 'Event updated successfully.',
                'data' => $responseDto->toArray(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Delete an event.
     *
     * DELETE /api/v1/events/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->eventService->deleteEvent($id, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Event deleted successfully.',
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Dedicated flyer upload directly to Cloudflare R2.
     *
     * POST /api/v1/events/{id}/flyer
     */
    public function uploadFlyer(UploadEventFlyerRequest $request, int $id): JsonResponse
    {
        try {
            $url = $this->eventService->uploadFlyer($id, $request->file('flyer'), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Flyer uploaded successfully.',
                'data' => [
                    'flyerUrl' => $url,
                ],
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Fast status update.
     *
     * PATCH /api/v1/events/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,published,ongoing,completed,cancelled'],
        ]);

        try {
            $event = $this->eventService->updateStatus($id, $validated['status'], $request->user());
            $responseDto = EventResponseDTO::fromModel($event);

            return response()->json([
                'status' => 'success',
                'message' => 'Event status updated successfully.',
                'data' => $responseDto->toArray(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Publicly discoverable published events directory.
     *
     * GET /api/v1/events/public
     */
    public function publicEvents(Request $request): JsonResponse
    {
        $filters = $request->all();
        $perPage = min(100, max(1, (int) $request->input('perPage', 15)));

        $paginator = $this->eventService->listPublicEvents($filters, $perPage);
        $listDto = EventListResponseDTO::fromPaginator($paginator);

        return response()->json([
            'status' => 'success',
            'message' => 'Public events retrieved successfully.',
            'data' => $listDto->toArray(),
        ]);
    }
}
