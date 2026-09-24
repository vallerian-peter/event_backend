<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inquiries\CreateProjectBriefRequest;
use App\Http\Requests\Inquiries\UpdateProjectBriefStatusRequest;
use App\Models\ProjectBrief;
use App\Services\InquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectBriefController extends Controller
{
    public function __construct(
        protected InquiryService $inquiryService
    ) {}

    /**
     * Submit a project brief / event order inquiry.
     *
     * POST /api/v1/public/inquiries/project-brief
     * POST /api/v1/inquiries/project-brief
     */
    public function store(CreateProjectBriefRequest $request): JsonResponse
    {
        $brief = $this->inquiryService->createProjectBrief($request->normalizedData());

        return response()->json([
            'status' => 'success',
            'message' => 'Your event project brief has been received successfully! Our strategists will reach out within 24 hours.',
            'data' => [
                'id' => $brief->id,
                'referenceCode' => $brief->referenceCode,
                'fullName' => $brief->fullName,
                'email' => $brief->email,
                'phone' => $brief->phone,
                'eventType' => $brief->eventType,
                'eventDate' => $brief->eventDate?->toDateString(),
                'status' => $brief->status,
                'createdAt' => $brief->createdAt,
            ],
        ], 201);
    }

    /**
     * List all project briefs (admin/concierge dashboard).
     *
     * GET /api/v1/inquiries/project-briefs
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 15);

        $briefs = $this->inquiryService->listProjectBriefs($status, $search, $perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Project briefs retrieved successfully.',
            'data' => $briefs,
        ]);
    }

    /**
     * View details of a specific project brief.
     *
     * GET /api/v1/inquiries/project-briefs/{id}
     */
    public function show(int $id): JsonResponse
    {
        $brief = ProjectBrief::with('assignedTo')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Project brief retrieved successfully.',
            'data' => $brief,
        ]);
    }

    /**
     * Update status and notes for a project brief.
     *
     * PATCH /api/v1/inquiries/project-briefs/{id}/status
     */
    public function updateStatus(UpdateProjectBriefStatusRequest $request, int $id): JsonResponse
    {
        $brief = $this->inquiryService->updateProjectBriefStatus(
            id: $id,
            status: $request->validated('status'),
            adminNotes: $request->validated('adminNotes'),
            assignedToUserId: $request->validated('assignedToUserId')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Project brief status updated successfully.',
            'data' => $brief,
        ]);
    }
}
