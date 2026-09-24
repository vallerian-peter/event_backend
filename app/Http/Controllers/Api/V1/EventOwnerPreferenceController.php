<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ServicePackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventOwnerPreferenceController extends Controller
{
    public function __construct(
        protected ServicePackageService $packageService
    ) {}

    /**
     * Get authenticated event owner's preferences.
     *
     * GET /api/v1/event-owners/preferences
     */
    public function show(Request $request): JsonResponse
    {
        $preferences = $this->packageService->getOwnerPreferences($request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Event owner preferences retrieved successfully.',
            'data' => $preferences,
        ]);
    }

    /**
     * Update event owner's preferences.
     *
     * PUT/PATCH /api/v1/event-owners/preferences
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'eventLanguage' => ['nullable', 'string', 'in:sw,en'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'preferredChannels' => ['nullable', 'array'],
            'committeeSettings' => ['nullable', 'array'],
            'messagePreferences' => ['nullable', 'array'],
        ]);

        $preferences = $this->packageService->updateOwnerPreferences($request->user(), $data);

        return response()->json([
            'status' => 'success',
            'message' => 'Event owner preferences updated successfully.',
            'data' => $preferences,
        ]);
    }
}
