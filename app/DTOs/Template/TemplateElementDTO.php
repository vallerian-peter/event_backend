<?php

namespace App\DTOs\Template;

use App\Enums\TemplateElementType;

/**
 * Data Transfer Object for creating or updating a Template Element position/style.
 */
class TemplateElementDTO
{
    public function __construct(
        public readonly TemplateElementType $elementType,
        public readonly int $positionX,
        public readonly int $positionY,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?int $fontSize = 36,
        public readonly ?string $fontColor = '#1E293B',
        public readonly ?string $fontFamily = 'Outfit',
        public readonly string $alignment = 'center',
        public readonly bool $isRequired = true,
        public readonly bool $isActive = true,
        public readonly ?int $id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rawType = $data['elementType'] ?? $data['type'] ?? 'qr_code';
        $type = $rawType instanceof TemplateElementType
            ? $rawType
            : (TemplateElementType::tryFrom((string) $rawType) ?? TemplateElementType::QrCode);

        return new self(
            elementType: $type,
            positionX: (int) ($data['positionX'] ?? $data['x'] ?? 0),
            positionY: (int) ($data['positionY'] ?? $data['y'] ?? 0),
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
            fontSize: isset($data['fontSize']) ? (int) $data['fontSize'] : 36,
            fontColor: isset($data['fontColor']) ? (string) $data['fontColor'] : '#1E293B',
            fontFamily: isset($data['fontFamily']) ? (string) $data['fontFamily'] : 'Outfit',
            alignment: (string) ($data['alignment'] ?? 'center'),
            isRequired: (bool) ($data['isRequired'] ?? true),
            isActive: (bool) ($data['isActive'] ?? true),
            id: isset($data['id']) ? (int) $data['id'] : null,
        );
    }

    /**
     * Convert to database attributes.
     *
     * @return array<string, mixed>
     */
    public function toModelAttributes(int $templateId): array
    {
        return [
            'templateId' => $templateId,
            'elementType' => $this->elementType->value,
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
        ];
    }
}
