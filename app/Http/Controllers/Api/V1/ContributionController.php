<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Contribution\ContributionListResponseDTO;
use App\DTOs\Contribution\ContributionResponseDTO;
use App\DTOs\Contribution\CreateContributionDTO;
use App\DTOs\Contribution\UpdateContributionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contribution\CreateContributionRequest;
use App\Http\Requests\Contribution\UpdateContributionRequest;
use App\Services\ContributionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContributionController extends Controller
{
    public function __construct(
        protected ContributionService $contributionService
    ) {}

    /**
     * List contributions with filters.
     *
     * GET /api/v1/contributions
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['eventId', 'inviteeId', 'status', 'division', 'search']);
        $perPage = max(1, min(100, (int) $request->input('perPage', 15)));

        $paginator = $this->contributionService->listContributions($filters, $request->user(), $perPage);
        $responseDto = ContributionListResponseDTO::fromPaginator($paginator);

        return response()->json([
            'status' => 'success',
            'message' => 'Contributions retrieved successfully.',
            'data' => $responseDto->toArray(),
        ]);
    }

    /**
     * Create a new contribution.
     *
     * POST /api/v1/contributions
     */
    public function store(CreateContributionRequest $request): JsonResponse
    {
        try {
            $dto = CreateContributionDTO::fromRequest($request);
            $contribution = $this->contributionService->createContribution($dto, $request->user());
            $responseDto = ContributionResponseDTO::fromModel($contribution);

            return response()->json([
                'status' => 'success',
                'message' => 'Contribution recorded successfully.',
                'data' => $responseDto->toArray(),
            ], 201);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Get single contribution details.
     *
     * GET /api/v1/contributions/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $contribution = $this->contributionService->getContribution($id, $request->user());
            $responseDto = ContributionResponseDTO::fromModel($contribution);

            return response()->json([
                'status' => 'success',
                'message' => 'Contribution retrieved successfully.',
                'data' => $responseDto->toArray(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Contribution not found.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Update an existing contribution.
     *
     * PUT/PATCH /api/v1/contributions/{id}
     */
    public function update(UpdateContributionRequest $request, int $id): JsonResponse
    {
        try {
            $dto = UpdateContributionDTO::fromRequest($request);
            $contribution = $this->contributionService->updateContribution($id, $dto, $request->user());
            $responseDto = ContributionResponseDTO::fromModel($contribution);

            return response()->json([
                'status' => 'success',
                'message' => 'Contribution updated successfully.',
                'data' => $responseDto->toArray(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Contribution not found.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Delete a contribution.
     *
     * DELETE /api/v1/contributions/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->contributionService->deleteContribution($id, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Contribution deleted successfully.',
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Contribution not found.',
            ], 404);
        } catch (AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}
