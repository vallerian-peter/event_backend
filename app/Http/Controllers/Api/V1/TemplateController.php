<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Template\CreateTemplateDTO;
use App\DTOs\Template\TemplateListResponseDTO;
use App\DTOs\Template\TemplateResponseDTO;
use App\DTOs\Template\UpdateTemplateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Template\CreateTemplateRequest;
use App\Http\Requests\Template\SyncTemplatePositionsRequest;
use App\Http\Requests\Template\UpdateTemplateRequest;
use App\Http\Requests\Template\UploadTemplateFileRequest;
use App\Services\TemplateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Controller handling Templates CRUD API (V1) endpoints and element position synchronization.
 */
class TemplateController extends Controller
{
    public function __construct(
        protected readonly TemplateService $templateService
    ) {}

    /**
     * List templates with filters and pagination.
     *
     * GET /api/v1/templates
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->all();
        $perPage = min(100, max(1, (int) $request->input('perPage', 15)));

        $paginator = $this->templateService->listTemplates($filters, $perPage);
        $listDto = TemplateListResponseDTO::fromPaginator($paginator);

        return response()->json([
            'status' => 'success',
            'message' => 'Templates retrieved successfully.',
            'data' => $listDto->toArray(),
        ]);
    }

    /**
     * Create a new template with optional element positions.
     *
     * POST /api/v1/templates
     */
    public function store(CreateTemplateRequest $request): JsonResponse
    {
        $dto = CreateTemplateDTO::fromRequest($request);
        $file = $request->file('file');
        $previewFile = $request->file('previewFile');

        $template = $this->templateService->createTemplate($dto, $file, $previewFile);
        $responseDto = TemplateResponseDTO::fromModel($template);

        return response()->json([
            'status' => 'success',
            'message' => 'Template created successfully.',
            'data' => $responseDto->toArray(),
        ], 201);
    }

    /**
     * Retrieve a single template with element positions.
     *
     * GET /api/v1/templates/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $template = $this->templateService->getTemplate($id);
            $responseDto = TemplateResponseDTO::fromModel($template);

            return response()->json([
                'status' => 'success',
                'message' => 'Template retrieved successfully.',
                'data' => $responseDto->toArray(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template not found.',
            ], 404);
        }
    }

    /**
     * Update an existing template and elements.
     *
     * PUT/PATCH /api/v1/templates/{id}
     */
    public function update(UpdateTemplateRequest $request, int $id): JsonResponse
    {
        try {
            $dto = UpdateTemplateDTO::fromRequest($request);
            $file = $request->file('file');
            $previewFile = $request->file('previewFile');

            $template = $this->templateService->updateTemplate($id, $dto, $file, $previewFile);
            $responseDto = TemplateResponseDTO::fromModel($template);

            return response()->json([
                'status' => 'success',
                'message' => 'Template updated successfully.',
                'data' => $responseDto->toArray(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template not found.',
            ], 404);
        }
    }

    /**
     * Delete a template.
     *
     * DELETE /api/v1/templates/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $force = $request->boolean('force', false);
            $this->templateService->deleteTemplate($id, $force);

            return response()->json([
                'status' => 'success',
                'message' => 'Template deleted successfully.',
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template not found.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Synchronize element positions specifically for QR Code and Invitee Name.
     *
     * PUT /api/v1/templates/{id}/positions
     */
    public function syncPositions(SyncTemplatePositionsRequest $request, int $id): JsonResponse
    {
        try {
            $template = $this->templateService->syncPositions($id, $request->validated());
            $responseDto = TemplateResponseDTO::fromModel($template);

            return response()->json([
                'status' => 'success',
                'message' => 'Template positions updated successfully.',
                'data' => $responseDto->toArray(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template not found.',
            ], 404);
        }
    }

    /**
     * Dedicated file upload for template background or preview image.
     *
     * POST /api/v1/templates/{id}/file
     */
    public function uploadFile(UploadTemplateFileRequest $request, int $id): JsonResponse
    {
        try {
            $type = (string) $request->input('type', 'template');
            $url = $this->templateService->uploadFile($id, $request->file('file'), $type);

            return response()->json([
                'status' => 'success',
                'message' => ucfirst($type).' image uploaded successfully.',
                'data' => [
                    'url' => $url,
                    'type' => $type,
                ],
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template not found.',
            ], 404);
        }
    }
}
