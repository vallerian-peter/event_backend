<?php

namespace App\Services;

use App\DTOs\Template\CreateTemplateDTO;
use App\DTOs\Template\TemplateElementDTO;
use App\DTOs\Template\UpdateTemplateDTO;
use App\Enums\TemplateElementType;
use App\Models\Template;
use App\Models\TemplateElement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Service class handling Template and TemplateElement CRUD operations,
 * storage management, and coordinate position syncing.
 */
class TemplateService
{
    /**
     * List templates with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listTemplates(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Template::query()
            ->with('elements')
            ->withCount('events');

        // Template type filter (invitation_card, ticket_pass, reminder_card)
        if (! empty($filters['templateType'])) {
            $query->where('templateType', $filters['templateType']);
        }

        // Category filter
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Premium filter
        if (isset($filters['isPremium'])) {
            $query->where('isPremium', filter_var($filters['isPremium'], FILTER_VALIDATE_BOOLEAN));
        }

        // Active filter
        if (isset($filters['isActive'])) {
            $query->where('isActive', filter_var($filters['isActive'], FILTER_VALIDATE_BOOLEAN));
        }

        // Render mode filter
        if (! empty($filters['renderMode'])) {
            $query->where('renderMode', $filters['renderMode']);
        }

        // Text search (name, category)
        if (! empty($filters['query'])) {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $searchTerm = '%'.trim((string) $filters['query']).'%';
            $query->where(function ($q) use ($searchTerm, $like): void {
                $q->where('name', $like, $searchTerm)
                    ->orWhere('category', $like, $searchTerm);
            });
        }

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Retrieve single template by ID.
     */
    public function getTemplate(int $id): Template
    {
        return Template::with('elements')
            ->withCount('events')
            ->findOrFail($id);
    }

    /**
     * Create a new template with background image and optional element positions.
     *
     * @throws \Throwable
     */
    public function createTemplate(
        CreateTemplateDTO $dto,
        ?UploadedFile $file = null,
        ?UploadedFile $previewFile = null
    ): Template {
        $pathUrl = $dto->pathUrl;
        $previewImageUrl = $dto->previewImageUrl;

        // 1. Upload background template file if provided
        if ($file) {
            $storedPath = $file->store('templates/backgrounds', 'r2');
            $pathUrl = Storage::disk('r2')->url($storedPath);
        }

        // 2. Upload preview image if provided
        if ($previewFile) {
            $storedPreview = $previewFile->store('templates/previews', 'r2');
            $previewImageUrl = Storage::disk('r2')->url($storedPreview);
        }

        return DB::transaction(function () use ($dto, $pathUrl, $previewImageUrl): Template {
            $attributes = $dto->toModelAttributes();
            $attributes['pathUrl'] = $pathUrl ?? '';
            $attributes['previewImageUrl'] = $previewImageUrl;

            /** @var Template $template */
            $template = Template::create($attributes);

            // Persist element coordinates & configurations
            foreach ($dto->elements as $elementDto) {
                $elementAttributes = $elementDto->toModelAttributes($template->id);
                TemplateElement::create($elementAttributes);
            }

            return $template->load('elements');
        });
    }

    /**
     * Update an existing template.
     *
     * @throws \Throwable
     */
    public function updateTemplate(
        int $id,
        UpdateTemplateDTO $dto,
        ?UploadedFile $file = null,
        ?UploadedFile $previewFile = null
    ): Template {
        $template = Template::with('elements')->findOrFail($id);

        $attributes = $dto->attributes;

        if ($file) {
            $storedPath = $file->store('templates/backgrounds', 'r2');
            $attributes['pathUrl'] = Storage::disk('r2')->url($storedPath);
        } elseif ($dto->pathUrl) {
            $attributes['pathUrl'] = $dto->pathUrl;
        }

        if ($previewFile) {
            $storedPreview = $previewFile->store('templates/previews', 'r2');
            $attributes['previewImageUrl'] = Storage::disk('r2')->url($storedPreview);
        } elseif ($dto->previewImageUrl) {
            $attributes['previewImageUrl'] = $dto->previewImageUrl;
        }

        return DB::transaction(function () use ($template, $attributes, $dto): Template {
            if (! empty($attributes)) {
                $template->update($attributes);
            }

            // Sync elements if provided
            if ($dto->elements !== null) {
                $template->elements()->delete();
                foreach ($dto->elements as $elementDto) {
                    $elementAttributes = $elementDto->toModelAttributes($template->id);
                    TemplateElement::create($elementAttributes);
                }
            }

            return $template->fresh(['elements'])->loadCount('events');
        });
    }

    /**
     * Dedicated method to sync or adjust positions for QR Code, Invitee Name, and other elements.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \Throwable
     */
    public function syncPositions(int $id, array $payload): Template
    {
        $template = Template::with('elements')->findOrFail($id);

        return DB::transaction(function () use ($template, $payload): Template {
            // 1. Handle direct qrCode coordinate update
            if (! empty($payload['qrCode']) && is_array($payload['qrCode'])) {
                $qrData = $payload['qrCode'];
                TemplateElement::updateOrCreate(
                    [
                        'templateId' => $template->id,
                        'elementType' => TemplateElementType::QrCode->value,
                    ],
                    [
                        'positionX' => (int) ($qrData['positionX'] ?? $qrData['x'] ?? 0),
                        'positionY' => (int) ($qrData['positionY'] ?? $qrData['y'] ?? 0),
                        'width' => isset($qrData['width']) ? (int) $qrData['width'] : 250,
                        'height' => isset($qrData['height']) ? (int) $qrData['height'] : 250,
                        'isRequired' => true,
                        'isActive' => true,
                    ]
                );
            }

            // 2. Handle direct inviteeName coordinate & style update
            if (! empty($payload['inviteeName']) && is_array($payload['inviteeName'])) {
                $nameData = $payload['inviteeName'];
                TemplateElement::updateOrCreate(
                    [
                        'templateId' => $template->id,
                        'elementType' => TemplateElementType::InviteeName->value,
                    ],
                    [
                        'positionX' => (int) ($nameData['positionX'] ?? $nameData['x'] ?? 0),
                        'positionY' => (int) ($nameData['positionY'] ?? $nameData['y'] ?? 0),
                        'width' => isset($nameData['width']) ? (int) $nameData['width'] : null,
                        'height' => isset($nameData['height']) ? (int) $nameData['height'] : null,
                        'fontSize' => isset($nameData['fontSize']) ? (int) $nameData['fontSize'] : 36,
                        'fontColor' => isset($nameData['fontColor']) ? (string) $nameData['fontColor'] : '#1E293B',
                        'fontFamily' => isset($nameData['fontFamily']) ? (string) $nameData['fontFamily'] : 'Outfit',
                        'alignment' => (string) ($nameData['alignment'] ?? 'center'),
                        'isRequired' => true,
                        'isActive' => true,
                    ]
                );
            }

            // 3. Handle elements array if passed
            if (! empty($payload['elements']) && is_array($payload['elements'])) {
                foreach ($payload['elements'] as $elementData) {
                    if (! is_array($elementData)) {
                        continue;
                    }

                    $dto = TemplateElementDTO::fromArray($elementData);
                    if ($dto->id) {
                        $el = TemplateElement::where('templateId', $template->id)->find($dto->id);
                        if ($el) {
                            $el->update($dto->toModelAttributes($template->id));
                            continue;
                        }
                    }

                    TemplateElement::updateOrCreate(
                        [
                            'templateId' => $template->id,
                            'elementType' => $dto->elementType->value,
                        ],
                        $dto->toModelAttributes($template->id)
                    );
                }
            }

            return $template->fresh(['elements'])->loadCount('events');
        });
    }

    /**
     * Dedicated file upload for template background or preview image.
     */
    public function uploadFile(int $id, UploadedFile $file, string $type = 'template'): string
    {
        $template = Template::findOrFail($id);

        if ($type === 'preview') {
            $path = $file->store('templates/previews', 'r2');
            $url = Storage::disk('r2')->url($path);
            $template->update(['previewImageUrl' => $url]);
        } else {
            $path = $file->store('templates/backgrounds', 'r2');
            $url = Storage::disk('r2')->url($path);
            $template->update(['pathUrl' => $url]);
        }

        return $url;
    }

    /**
     * Delete a template.
     *
     * @throws ValidationException
     */
    public function deleteTemplate(int $id, bool $force = false): void
    {
        $template = Template::withCount('events')->findOrFail($id);

        if ($template->events_count > 0 && ! $force) {
            throw ValidationException::withMessages([
                'template' => ["Cannot delete template #{$id} because it is actively used by {$template->events_count} event(s)."],
            ]);
        }

        $template->delete();
    }
}
