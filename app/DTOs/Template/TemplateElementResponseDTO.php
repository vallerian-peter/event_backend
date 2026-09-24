<?php

namespace App\DTOs\Template;

use App\Models\TemplateElement;

/**
 * Data Transfer Object for formatted TemplateElement JSON responses.
 */
class TemplateElementResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $templateId,
        public readonly string $elementType,
        public readonly int $positionX,
        public readonly int $positionY,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?int $fontSize,
        public readonly ?string $fontColor,
        public readonly ?string $fontFamily,
        public readonly string $alignment,
        public readonly bool $isRequired,
        public readonly bool $isActive,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public static function fromModel(TemplateElement $element): self
    {
        return new self(
            id: $element->id,
            templateId: $element->templateId,
            elementType: $element->elementType->value ?? (string) $element->elementType,
            positionX: $element->positionX,
            positionY: $element->positionY,
            width: $element->width,
            height: $element->height,
            fontSize: $element->fontSize,
            fontColor: $element->fontColor,
            fontFamily: $element->fontFamily,
            alignment: $element->alignment,
            isRequired: $element->isRequired,
            isActive: $element->isActive,
            createdAt: $element->createdAt?->toIso8601String(),
            updatedAt: $element->updatedAt?->toIso8601String(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'templateId' => $this->templateId,
            'elementType' => $this->elementType,
            'positionX' => $this->positionX,
            'positionY' => $this->positionY,
            'width' => $this->width,
            'height' => $this->height,
            'fontSize' => $this->fontSize,
            'fontColor' => $this->fontColor,
            'fontFamily' => $this->fontFamily,
            'alignment' => $this->alignment,
            'isRequired' => $this->isRequired,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
