<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Invitee\CreateInviteeDTO;
use App\DTOs\Invitee\InviteeListResponseDTO;
use App\DTOs\Invitee\InviteeResponseDTO;
use App\DTOs\Invitee\UpdateInviteeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitee\BulkCreateInviteesRequest;
use App\Http\Requests\Invitee\BulkUpdateInviteeStatusRequest;
use App\Http\Requests\Invitee\CheckInInviteeRequest;
use App\Http\Requests\Invitee\CreateInviteeRequest;
use App\Http\Requests\Invitee\UpdateInviteeRequest;
use App\Services\InviteeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Controller handling Invitee management API endpoints, bulk operations,
 * QR previewing, gate check-in, and stats.
 */
class InviteeController extends Controller
{
    public function __construct(
        protected readonly InviteeService $inviteeService
    ) {}

    /**
     * List invitees with filters and pagination.
     *
     * GET /api/v1/invitees
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->all();
        $perPage = min(1000, max(1, (int) $request->input('perPage', $request->input('pageSize', 15))));

        $paginator = $this->inviteeService->listInvitees($filters, $request->user(), $perPage);
        $listDto = InviteeListResponseDTO::fromPaginator($paginator);

        return response()->json([
            'status' => 'success',
            'message' => 'Invitees retrieved successfully.',
            'data' => $listDto->toArray(),
        ]);
    }

    /**
     * Create a new invitee.
     *
     * POST /api/v1/invitees
     */
    public function store(CreateInviteeRequest $request): JsonResponse
    {
        try {
            $dto = CreateInviteeDTO::fromRequest($request);
            $invitee = $this->inviteeService->createInvitee($dto, $request->user());
            $responseDto = InviteeResponseDTO::fromModel($invitee);

            return response()->json([
                'status' => 'success',
                'message' => 'Invitee created successfully.',
                'data' => $responseDto->toArray(),
            ], 201);
        } catch (ValidationException $e) {
            $limitReached = str_contains($e->getMessage(), 'Invitee limit reached');

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => $limitReached ? 'INVITEE_LIMIT_REACHED' : 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], $limitReached ? 409 : 422);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Retrieve single invitee by ID.
     *
     * GET /api/v1/invitees/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $invitee = $this->inviteeService->getInvitee($id, $request->user());
            $responseDto = InviteeResponseDTO::fromModel($invitee);

            return response()->json([
                'status' => 'success',
                'message' => 'Invitee retrieved successfully.',
                'data' => $responseDto->toArray(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invitee not found.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Update an existing invitee and contribution amounts.
     *
     * PUT/PATCH /api/v1/invitees/{id}
     */
    public function update(UpdateInviteeRequest $request, int $id): JsonResponse
    {
        try {
            $dto = UpdateInviteeDTO::fromRequest($request);
            $invitee = $this->inviteeService->updateInvitee($id, $dto, $request->user());
            $responseDto = InviteeResponseDTO::fromModel($invitee);

            return response()->json([
                'status' => 'success',
                'message' => 'Invitee updated successfully.',
                'data' => $responseDto->toArray(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invitee not found.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Delete an invitee.
     *
     * DELETE /api/v1/invitees/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->inviteeService->deleteInvitee($id, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Invitee deleted successfully.',
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invitee not found.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Dedicated QR preview endpoint matching event-management-system contract.
     *
     * GET /api/v1/invitees/{id}/qr-preview
     */
    public function qrPreview(Request $request, int $id): JsonResponse
    {
        try {
            $preview = $this->inviteeService->getQrPreview($id, $request->user());

            return response()->json($preview);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'available' => false,
                'error' => 'Invitee not found',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'available' => false,
                'error' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Bulk create invitees for an event.
     *
     * POST /api/v1/invitees/bulk
     */
    public function bulkStore(BulkCreateInviteesRequest $request): JsonResponse
    {
        try {
            $eventId = (int) $request->input('eventId');
            $inviteesData = (array) $request->input('invitees');

            $created = $this->inviteeService->bulkCreateInvitees($eventId, $inviteesData, $request->user());
            $results = array_map(fn ($inv) => InviteeResponseDTO::fromModel($inv)->toArray(), $created);

            return response()->json([
                'status' => 'success',
                'message' => count($results).' invitees created successfully.',
                'data' => [
                    'invitees' => $results,
                    'count' => count($results),
                ],
            ], 201);
        } catch (ValidationException $e) {
            $limitReached = str_contains($e->getMessage(), 'Invitee limit reached');

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => $limitReached ? 'INVITEE_LIMIT_REACHED' : 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], $limitReached ? 409 : 422);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Bulk update status for multiple invitees in an event.
     *
     * PATCH /api/v1/invitees/bulk-status
     */
    public function bulkStatus(BulkUpdateInviteeStatusRequest $request): JsonResponse
    {
        try {
            $eventId = (int) $request->input('eventId');
            $inviteeIds = (array) $request->input('inviteeIds', []);
            $status = (string) $request->input('status');

            $updatedCount = $this->inviteeService->bulkUpdateStatus($eventId, $inviteeIds, $status, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => "{$updatedCount} invitee(s) status updated to '{$status}'.",
                'data' => [
                    'updatedCount' => $updatedCount,
                    'status' => $status,
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
     * Summary statistics for an event's invitees.
     *
     * GET /api/v1/events/{eventId}/invitees/stats
     */
    public function stats(Request $request, int $eventId): JsonResponse
    {
        try {
            $statsDto = $this->inviteeService->getEventStats($eventId, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Invitee statistics retrieved successfully.',
                'data' => $statsDto->toArray(),
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
     * Check-in an invitee at the gate.
     *
     * POST /api/v1/invitees/{id}/check-in
     */
    public function checkIn(CheckInInviteeRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->inviteeService->checkIn($id, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invitee not found.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 409);
        }
    }
}
