<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Messaging\BroadcastMessageDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\BroadcastMessageRequest;
use App\Services\MessageBroadcastService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function __construct(
        protected MessageBroadcastService $broadcastService
    ) {}

    /**
     * Broadcast an on-demand message to event invitees.
     *
     * POST /api/v1/events/{eventId}/messages/send
     */
    public function send(BroadcastMessageRequest $request, int $eventId): JsonResponse
    {
        try {
            $dto = BroadcastMessageDTO::fromRequest($request, $eventId);
            $result = $this->broadcastService->broadcast($dto, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => "Message broadcast queued successfully for {$result['recipientCount']} recipient(s).",
                'data' => $result,
            ], 202);
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
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * List message campaigns for an event.
     *
     * GET /api/v1/events/{eventId}/messages
     */
    public function index(Request $request, int $eventId): JsonResponse
    {
        try {
            $perPage = max(1, min(100, (int) $request->input('perPage', 15)));
            $paginator = $this->broadcastService->getEventMessages($eventId, $request->user(), $perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Message campaigns retrieved successfully.',
                'data' => [
                    'messages' => $paginator->items(),
                    'meta' => [
                        'currentPage' => $paginator->currentPage(),
                        'perPage' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'lastPage' => $paginator->lastPage(),
                        'hasMorePages' => $paginator->hasMorePages(),
                    ],
                ],
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * List individual message delivery records for an event.
     *
     * GET /api/v1/events/{eventId}/messages/records
     */
    public function records(Request $request, int $eventId): JsonResponse
    {
        try {
            $perPage = max(1, min(100, (int) $request->input('perPage', 50)));
            $paginator = $this->broadcastService->getDeliveryRecords($eventId, $request->user(), $perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Delivery records retrieved successfully.',
                'data' => [
                    'records' => $paginator->items(),
                    'meta' => [
                        'currentPage' => $paginator->currentPage(),
                        'perPage' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'lastPage' => $paginator->lastPage(),
                        'hasMorePages' => $paginator->hasMorePages(),
                    ],
                ],
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Get feature balances (SMS / WhatsApp quotas) for an event.
     *
     * GET /api/v1/events/{eventId}/features/balance
     */
    public function featureBalance(Request $request, int $eventId): JsonResponse
    {
        try {
            $balances = $this->broadcastService->getFeatureBalances($eventId, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Feature balances retrieved successfully.',
                'data' => $balances,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }
}
