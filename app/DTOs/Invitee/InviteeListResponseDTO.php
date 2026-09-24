<?php

namespace App\DTOs\Invitee;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Data Transfer Object for paginated list of invitees.
 */
class InviteeListResponseDTO
{
    /**
     * @param  InviteeResponseDTO[]  $invitees
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly array $invitees,
        public readonly array $meta,
    ) {}

    /**
     * Create from Laravel LengthAwarePaginator.
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        $items = [];
        foreach ($paginator->items() as $invitee) {
            $items[] = InviteeResponseDTO::fromModel($invitee);
        }

        $meta = [
            'currentPage' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'hasMorePages' => $paginator->hasMorePages(),
        ];

        return new self(
            invitees: $items,
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
            'invitees' => array_map(fn (InviteeResponseDTO $dto) => $dto->toArray(), $this->invitees),
            'meta' => $this->meta,
        ];
    }
}
