<?php

namespace App\DTOs\Template;

use App\Enums\TemplateRenderMode;
use App\Enums\TemplateType;
use Illuminate\Http\Request;

/**
 * Data Transfer Object for creating a new Template.
 */
class CreateTemplateDTO
{
    /**
     * @param  TemplateElementDTO[]  $elements
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $pathUrl = null,
        public readonly ?string $previewImageUrl = null,
        public readonly float $price = 0.00,
        public readonly bool $isPremium = false,
        public readonly ?string $category = null,
        public readonly string $templateType = 'invitation_card',
        public readonly int $canvasWidth = 1080,
        public readonly int $canvasHeight = 1920,
        public readonly string $renderMode = 'qr_and_name',
        public readonly bool $isActive = true,
        public readonly array $elements = [],
    ) {}

    /**
     * Create DTO from validated Form Request or HTTP Request.
     */
    public static function fromRequest(Request $request): self
    {
        $elements = [];
        if ($request->has('elements') && is_array($request->input('elements'))) {
            foreach ($request->input('elements') as $elementData) {
                if (is_array($elementData)) {
                    $elements[] = TemplateElementDTO::fromArray($elementData);
                }
            }
        }

        // Support direct qrCode and name element shortcuts in request if provided
        if ($request->has('qrCode') && is_array($request->input('qrCode'))) {
            $qrData = $request->input('qrCode');
            $qrData['elementType'] = 'qr_code';
            $elements[] = TemplateElementDTO::fromArray($qrData);
        }

        if ($request->has('inviteeName') && is_array($request->input('inviteeName'))) {
            $nameData = $request->input('inviteeName');
            $nameData['elementType'] = 'invitee_name';
            $elements[] = TemplateElementDTO::fromArray($nameData);
        }

        $templateType = $request->input('templateType', 'invitation_card');
        if ($templateType instanceof TemplateType) {
            $templateType = $templateType->value;
        }

        $renderMode = $request->input('renderMode', 'qr_and_name');
        if ($renderMode instanceof TemplateRenderMode) {
            $renderMode = $renderMode->value;
        }

        return new self(
            name: (string) $request->input('name', 'Template'),
            pathUrl: $request->filled('pathUrl') ? (string) $request->input('pathUrl') : null,
            previewImageUrl: $request->filled('previewImageUrl') ? (string) $request->input('previewImageUrl') : null,
            price: (float) $request->input('price', 0.00),
            isPremium: $request->boolean('isPremium', false),
            category: $request->filled('category') ? (string) $request->input('category') : null,
            templateType: (string) $templateType,
            canvasWidth: (int) $request->input('canvasWidth', 1080),
            canvasHeight: (int) $request->input('canvasHeight', 1920),
            renderMode: (string) $renderMode,
            isActive: $request->boolean('isActive', true),
            elements: $elements,
        );
    }

    /**
     * Convert properties to array for Template model creation.
     *
     * @return array<string, mixed>
     */
    public function toModelAttributes(): array
    {
        return [
            'name' => $this->name,
            'pathUrl' => $this->pathUrl ?? '',
            'previewImageUrl' => $this->previewImageUrl,
            'price' => $this->price,
            'isPremium' => $this->isPremium,
            'category' => $this->category,
            'templateType' => $this->templateType,
            'canvasWidth' => $this->canvasWidth,
            'canvasHeight' => $this->canvasHeight,
            'renderMode' => $this->renderMode,
            'isActive' => $this->isActive,
        ];
    }
}
