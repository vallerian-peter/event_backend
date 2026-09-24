<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TicketingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicEventController extends Controller
{
    public function __construct(
        protected TicketingService $ticketingService
    ) {}

    /**
     * List public events catalog with filters.
     *
     * GET /api/v1/public/events
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category', 'typeOfEvent']);
        $perPage = (int) $request->query('per_page', 15);

        $events = $this->ticketingService->listPublicEvents($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Public events catalog retrieved successfully.',
            'data' => $events,
        ]);
    }

    /**
     * Get public event details and available ticket types by slug.
     *
     * GET /api/v1/public/events/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        try {
            $data = $this->ticketingService->getPublicEventBySlug($slug);

            return response()->json([
                'status' => 'success',
                'message' => 'Public event retrieved successfully.',
                'data' => $data,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Public event not found.',
            ], 404);
        }
    }
}
