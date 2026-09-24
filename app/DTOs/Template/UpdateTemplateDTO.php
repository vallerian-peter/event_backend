<?php

namespace App\DTOs\Template;

use App\Enums\TemplateRenderMode;
use App\Enums\TemplateType;
use Illuminate\Http\Request;

/**
 * Data Transfer Object for updating an existing Template.
 */
class UpdateTemplateDTO
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  TemplateElementDTO[]|null  $elements
     */
    public function __construct(
        public readonly array $attributes,
        public readonly ?array $elements = null,
        public readonly ?string $pathUrl = null,
        public readonly ?string $previewImageUrl = null,
    ) {}

    /**
     * Create DTO from validated Form Request or HTTP Request.
     */
    public static function fromRequest(Request $request): self
    {
        $attributes = [];

        $directKeys = [
            'name',
            'pathUrl',
            'previewImageUrl',
            'category',
        ];

        foreach ($directKeys as $key) {
            if ($request->exists($key)) {
                $attributes[$key] = $request->input($key);
            }
        }

        if ($request->exists('price')) {
            $attributes['price'] = (float) $request->input('price');
        }

        if ($request->exists('canvasWidth')) {
            $attributes['canvasWidth'] = (int) $request->input('canvasWidth');
        }

        if ($request->exists('canvasHeight')) {
            $attributes['canvasHeight'] = (int) $request->input('canvasHeight');
        }

        if ($request->exists('isPremium')) {
            $attributes['isPremium'] = $request->boolean('isPremium');
        }

        if ($request->exists('isActive')) {
            $attributes['isActive'] = $request->boolean('isActive');
        }

        if ($request->exists('templateType')) {
            $type = $request->input('templateType');
            $attributes['templateType'] = $type instanceof TemplateType ? $type->value : (string) $type;
        }

        if ($request->exists('renderMode')) {
            $mode = $request->input('renderMode');
            $attributes['renderMode'] = $mode instanceof TemplateRenderMode ? $mode->value : (string) $mode;
        }

        $elements = null;
        if ($request->has('elements') && is_array($request->input('elements'))) {
            $elements = [];
            foreach ($request->input('elements') as $elementData) {
                if (is_array($elementData)) {
                    $elements[] = TemplateElementDTO::fromArray($elementData);
                }
            }
        }

        return new self(
            attributes: $attributes,
            elements: $elements,
            pathUrl: $request->filled('pathUrl') ? (string) $request->input('pathUrl') : null,
            previewImageUrl: $request->filled('previewImageUrl') ? (string) $request->input('previewImageUrl') : null,
        );
    }
}
