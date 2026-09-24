<?php

namespace App\DTOs\Template;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Data Transfer Object for paginated list of templates.
 */
class TemplateListResponseDTO
{
    /**
     * @param  TemplateResponseDTO[]  $templates
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly array $templates,
        public readonly array $meta,
    ) {}

    /**
     * Create from Laravel LengthAwarePaginator.
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        $items = [];
        foreach ($paginator->items() as $template) {
            $items[] = TemplateResponseDTO::fromModel($template);
        }

        $meta = [
            'currentPage' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'hasMorePages' => $paginator->hasMorePages(),
        ];

        return new self(
            templates: $items,
            meta: $meta,
        );
    }

    /**
     * Convert to JSON-serializable array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'templates' => array_map(fn (TemplateResponseDTO $dto) => $dto->toArray(), $this->templates),
            'meta' => $this->meta,
        ];
    }
}
