<?php

namespace App\DTOs\Template;

use App\Models\Template;
use App\Models\TemplateElement;

/**
 * Data Transfer Object for formatted Template JSON responses with strict camelCase keys.
 */
class TemplateResponseDTO
{
    /**
     * @param  array<int, array<string, mixed>>  $elements
     * @param  array<string, mixed>|null  $qrPosition
     * @param  array<string, mixed>|null  $namePosition
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $pathUrl,
        public readonly ?string $previewImageUrl,
        public readonly float $price,
        public readonly bool $isPremium,
        public readonly ?string $category,
        public readonly string $templateType,
        public readonly int $canvasWidth,
        public readonly int $canvasHeight,
        public readonly string $renderMode,
        public readonly bool $isActive,
        public readonly array $elements = [],
        public readonly ?array $qrPosition = null,
        public readonly ?array $namePosition = null,
        public readonly int $eventsCount = 0,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public static function fromModel(Template $template): self
    {
        $elements = [];
        $qrPos = null;
        $namePos = null;

        if ($template->relationLoaded('elements')) {
            foreach ($template->elements as $element) {
                $elementDto = TemplateElementResponseDTO::fromModel($element);
                $elements[] = $elementDto->toArray();

                $typeVal = $element->elementType->value ?? (string) $element->elementType;
                if ($typeVal === 'qr_code' && $qrPos === null) {
                    $qrPos = [
                        'elementId' => $element->id,
                        'positionX' => $element->positionX,
                        'positionY' => $element->positionY,
                        'width' => $element->width,
                        'height' => $element->height,
                    ];
                } elseif ($typeVal === 'invitee_name' && $namePos === null) {
                    $namePos = [
                        'elementId' => $element->id,
                        'positionX' => $element->positionX,
                        'positionY' => $element->positionY,
                        'width' => $element->width,
                        'height' => $element->height,
                        'fontSize' => $element->fontSize,
                        'fontColor' => $element->fontColor,
                        'fontFamily' => $element->fontFamily,
                        'alignment' => $element->alignment,
                    ];
                }
            }
        }

        return new self(
            id: $template->id,
            name: $template->name,
            pathUrl: $template->pathUrl,
            previewImageUrl: $template->previewImageUrl,
            price: (float) $template->price,
            isPremium: (bool) $template->isPremium,
            category: $template->category,
            templateType: $template->templateType->value ?? (string) $template->templateType,
            canvasWidth: (int) $template->canvasWidth,
            canvasHeight: (int) $template->canvasHeight,
            renderMode: $template->renderMode->value ?? (string) $template->renderMode,
            isActive: (bool) $template->isActive,
            elements: $elements,
            qrPosition: $qrPos,
            namePosition: $namePos,
            eventsCount: (int) ($template->events_count ?? 0),
            createdAt: $template->createdAt?->toIso8601String(),
            updatedAt: $template->updatedAt?->toIso8601String(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'pathUrl' => $this->pathUrl,
            'previewImageUrl' => $this->previewImageUrl,
            'price' => $this->price,
            'isPremium' => $this->isPremium,
            'category' => $this->category,
            'templateType' => $this->templateType,
            'canvasWidth' => $this->canvasWidth,
            'canvasHeight' => $this->canvasHeight,
            'renderMode' => $this->renderMode,
            'isActive' => $this->isActive,
            'elements' => $this->elements,
            'qrPosition' => $this->qrPosition,
            'namePosition' => $this->namePosition,
            'eventsCount' => $this->eventsCount,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
