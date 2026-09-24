<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ServicePackageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicePackageController extends Controller
{
    public function __construct(
        protected ServicePackageService $packageService
    ) {}

    /**
     * List all available service packages.
     *
     * GET /api/v1/service-packages
     */
    public function index(): JsonResponse
    {
        $packages = $this->packageService->listPackages();

        return response()->json([
            'status' => 'success',
            'message' => 'Service packages retrieved successfully.',
            'data' => $packages,
        ]);
    }

    /**
     * Get a specific service package.
     *
     * GET /api/v1/service-packages/{idOrSlug}
     */
    public function show(string $idOrSlug): JsonResponse
    {
        try {
            $package = $this->packageService->getPackage($idOrSlug);

            return response()->json([
                'status' => 'success',
                'message' => 'Service package retrieved successfully.',
                'data' => $package,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Service package not found.'], 404);
        }
    }

    /**
     * Subscribe an event to a package.
     *
     * POST /api/v1/events/{eventId}/subscribe-package
     */
    public function subscribe(Request $request, int $eventId): JsonResponse
    {
        $request->validate([
            'servicePackageId' => ['required', 'integer', 'exists:service_packages,id'],
            'inviteeLimit' => ['nullable', 'integer', 'min:10'],
            'smsUnits' => ['nullable', 'integer', 'min:0'],
            'amountTzs' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $packageId = (int) $request->input('servicePackageId');
            $options = $request->only(['inviteeLimit', 'smsUnits', 'amountTzs', 'paymentId']);

            $result = $this->packageService->subscribeEvent($eventId, $packageId, $options, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Event subscribed to service package successfully.',
                'data' => $result,
            ], 201);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event or service package not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }

    /**
     * Get active subscription details and balances for an event.
     *
     * GET /api/v1/events/{eventId}/subscription
     */
    public function subscription(Request $request, int $eventId): JsonResponse
    {
        try {
            $data = $this->packageService->getEventSubscription($eventId, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Event subscription details retrieved successfully.',
                'data' => $data,
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Event not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }
    }
}
