<?php

namespace App\DTOs\Contribution;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContributionListResponseDTO
{
    /**
     * @param  ContributionResponseDTO[]  $contributions
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly array $contributions,
        public readonly array $meta,
    ) {}

    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        $items = [];
        foreach ($paginator->items() as $contribution) {
            $items[] = ContributionResponseDTO::fromModel($contribution);
        }

        $meta = [
            'currentPage' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'hasMorePages' => $paginator->hasMorePages(),
        ];

        return new self(
            contributions: $items,
            meta: $meta,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contributions' => array_map(fn (ContributionResponseDTO $dto) => $dto->toArray(), $this->contributions),
            'meta' => $this->meta,
        ];
    }
}
