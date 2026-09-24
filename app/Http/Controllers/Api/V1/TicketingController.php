<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\CreateTicketTypeRequest;
use App\Http\Requests\Ticketing\PublicTicketCheckoutRequest;
use App\Http\Requests\Ticketing\UpdateTicketTypeRequest;
use App\Services\TicketingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TicketingController extends Controller
{
    public function __construct(
        protected TicketingService $ticketingService
    ) {}

    /**
     * List ticket types for an event.
     *
     * GET /api/v1/events/{eventId}/ticket-types
     */
    public function indexTypes(Request $request, int $eventId): JsonResponse
    {
        try {
            $activeOnly = $request->boolean('active_only', false);
            $types = $this->ticketingService->listTicketTypes($eventId, $activeOnly, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Ticket types retrieved successfully.',
                'data' => $types,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Create a ticket type.
     *
     * POST /api/v1/events/{eventId}/ticket-types
     */
    public function storeType(CreateTicketTypeRequest $request, int $eventId): JsonResponse
    {
        try {
            $type = $this->ticketingService->createTicketType($eventId, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Ticket type created successfully.',
                'data' => $type,
            ], 201);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Get a single ticket type.
     *
     * GET /api/v1/ticket-types/{id}
     */
    public function showType(int $id): JsonResponse
    {
        try {
            $type = $this->ticketingService->getTicketType($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Ticket type retrieved successfully.',
                'data' => $type,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Ticket type not found.'], 404);
        }
    }

    /**
     * Update a ticket type.
     *
     * PUT/PATCH /api/v1/ticket-types/{id}
     */
    public function updateType(UpdateTicketTypeRequest $request, int $id): JsonResponse
    {
        try {
            $type = $this->ticketingService->updateTicketType($id, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Ticket type updated successfully.',
                'data' => $type,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Ticket type not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Delete or disable a ticket type.
     *
     * DELETE /api/v1/ticket-types/{id}
     */
    public function destroyType(Request $request, int $id): JsonResponse
    {
        try {
            $this->ticketingService->deleteTicketType($id, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Ticket type deleted or deactivated successfully.',
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Ticket type not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Public ticket checkout and purchase.
     *
     * POST /api/v1/public/events/{eventId}/tickets/checkout
     */
    public function checkout(PublicTicketCheckoutRequest $request, int $eventId): JsonResponse
    {
        try {
            $result = $this->ticketingService->checkout($eventId, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Ticket purchase order created successfully.',
                'data' => $result,
            ], 201);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event or ticket type not found.'], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Verify ticket validity.
     *
     * GET /api/v1/tickets/{code}/verify
     */
    public function verify(string $code): JsonResponse
    {
        try {
            $ticket = $this->ticketingService->verifyTicket($code);

            return response()->json([
                'status' => 'success',
                'message' => 'Ticket verified successfully.',
                'data' => $ticket,
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
     * Check in ticket at gate.
     *
     * POST /api/v1/tickets/{code}/check-in
     */
    public function checkIn(Request $request, string $code): JsonResponse
    {
        try {
            $user = $request->user();
            $gateKeeperId = ($user && $user->role === \App\Enums\UserRole::GateKeeper) ? $user->roleId : null;
            $gateName = $request->input('gateName', 'Main Gate');

            $result = $this->ticketingService->checkInTicket($code, $gateKeeperId, $gateName);

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => $result,
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
     * List ticket orders for an event.
     *
     * GET /api/v1/events/{eventId}/ticket-orders
     */
    public function indexOrders(Request $request, int $eventId): JsonResponse
    {
        try {
            $orders = $this->ticketingService->listOrders($eventId, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Ticket orders retrieved successfully.',
                'data' => $orders,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Get order details by order number.
     *
     * GET /api/v1/ticket-orders/{orderNumber}
     */
    public function showOrder(string $orderNumber): JsonResponse
    {
        try {
            $order = $this->ticketingService->getOrderByNumber($orderNumber);

            return response()->json([
                'status' => 'success',
                'message' => 'Ticket order retrieved successfully.',
                'data' => $order,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Ticket order not found.'], 404);
        }
    }
}
