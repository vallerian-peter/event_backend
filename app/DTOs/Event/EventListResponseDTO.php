<?php

namespace App\DTOs\Event;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Data Transfer Object for paginated list of events.
 */
class EventListResponseDTO
{
    /**
     * @param  EventResponseDTO[]  $events
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly array $events,
        public readonly array $meta,
    ) {}

    /**
     * Create from Laravel LengthAwarePaginator.
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        $items = [];
        foreach ($paginator->items() as $event) {
            $items[] = EventResponseDTO::fromModel($event);
        }

        $meta = [
            'currentPage' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'hasMorePages' => $paginator->hasMorePages(),
        ];

        return new self(
            events: $items,
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
            'events' => array_map(fn (EventResponseDTO $dto) => $dto->toArray(), $this->events),
            'meta' => $this->meta,
        ];
    }
}
