<?php

namespace App\Services;

use App\DTOs\Event\CreateEventDTO;
use App\DTOs\Event\UpdateEventDTO;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventOwner;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Service managing domain logic for Event CRUD, ticketing, and Cloudflare R2 flyer storage.
 */
class EventService
{
    /**
     * Retrieve paginated list of events scoped by user role and filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listEvents(array $filters, User $user, int $perPage = 15): LengthAwarePaginator
    {
        $query = Event::query()
            ->with(['owner', 'template', 'ticketTypes'])
            ->withCount(['invitees', 'ticketOrders']);

        // Scope by user role
        if ($user->role === UserRole::EventOwner || $user->role?->value === 'event_owner') {
            $ownerId = $user->eventOwner?->id ?? $user->roleId;
            $query->where('eventOwnerId', $ownerId);
        } elseif ($user->role === UserRole::Admin || $user->role?->value === 'admin') {
            if (! empty($filters['eventOwnerId'])) {
                $query->where('eventOwnerId', (int) $filters['eventOwnerId']);
            }
        }

        // Search query filter (title, venueName, description)
        if (! empty($filters['query'])) {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $searchTerm = '%'.trim((string) $filters['query']).'%';
            $query->where(function ($q) use ($searchTerm, $like): void {
                $q->where('title', $like, $searchTerm)
                    ->orWhere('venueName', $like, $searchTerm)
                    ->orWhere('description', $like, $searchTerm);
            });
        }

        // Event category filter ('normal' | 'ticketed')
        if (! empty($filters['eventCategory'])) {
            $query->where('eventCategory', $filters['eventCategory']);
        }

        // Status filter
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Type of event filter
        if (! empty($filters['typeOfEvent'])) {
            $query->where('typeOfEvent', $filters['typeOfEvent']);
        }

        // Date range filters
        if (! empty($filters['startDate'])) {
            $query->whereDate('startDate', '>=', $filters['startDate']);
        }
        if (! empty($filters['endDate'])) {
            $query->whereDate('endDate', '<=', $filters['endDate']);
        }

        // Public / Featured flags
        if (isset($filters['isPublic'])) {
            $query->where('isPublic', filter_var($filters['isPublic'], FILTER_VALIDATE_BOOLEAN));
        }
        if (isset($filters['isFeatured'])) {
            $query->where('isFeatured', filter_var($filters['isFeatured'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderByDesc('createdAt')->paginate($perPage);
    }

    /**
     * Create a new event with optional flyer upload and initial ticket types.
     */
    public function createEvent(CreateEventDTO $dto, ?UploadedFile $flyerFile, User $user): Event
    {
        // 1. Resolve eventOwnerId
        $eventOwnerId = $dto->eventOwnerId;
        if ($user->role === UserRole::EventOwner || $user->role?->value === 'event_owner') {
            $eventOwnerId = $user->eventOwner?->id ?? $user->roleId;
        }

        // 2. Handle flyer upload to Cloudflare R2 if file provided
        $flyerUrl = $dto->flyerUrl;
        if ($flyerFile) {
            $path = $flyerFile->store('events/flyers', 'r2');
            $flyerUrl = Storage::disk('r2')->url($path);
        }

        $attributes = $dto->toModelAttributes();
        $attributes['eventOwnerId'] = $eventOwnerId;
        if ($flyerUrl) {
            $attributes['flyerUrl'] = $flyerUrl;
            $attributes['coverImageUrl'] = $flyerUrl;
        }

        // 3. Persist Event and TicketTypes in transaction
        return DB::transaction(function () use ($attributes, $dto, $eventOwnerId): Event {
            $event = Event::create($attributes);

            // Create ticket types for ticketed events
            if (! empty($dto->ticketTypes)) {
                foreach ($dto->ticketTypes as $ticketDto) {
                    TicketType::create($ticketDto->toModelAttributes($event->id));
                }
            }

            // Sync with eventOwner.eventIds array
            if ($eventOwnerId) {
                $owner = EventOwner::find($eventOwnerId);
                if ($owner) {
                    $currentIds = $owner->eventIds ?? [];
                    if (! in_array($event->id, $currentIds)) {
                        $currentIds[] = $event->id;
                        $owner->update(['eventIds' => array_values($currentIds)]);
                    }
                }
            }

            return $event->load(['owner', 'template', 'ticketTypes']);
        });
    }

    /**
     * Get single event by ID or unique slug with authorization check.
     *
     * @throws AuthorizationException
     */
    public function getEvent(int|string $idOrSlug, ?User $user = null): Event
    {
        $query = Event::query()
            ->with(['owner', 'template', 'ticketTypes'])
            ->withCount(['invitees', 'ticketOrders']);

        if (is_numeric($idOrSlug)) {
            $event = $query->findOrFail((int) $idOrSlug);
        } else {
            $event = $query->where('slug', $idOrSlug)->firstOrFail();
        }

        // Publicly visible published events can be viewed by anyone
        if ($event->isPublic && $event->status === EventStatus::Published) {
            return $event;
        }

        // If not public or not published, user must be authenticated and authorized
        if (! $user) {
            throw new AuthorizationException('This event is private or not yet published.');
        }

        if ($user->role === UserRole::Admin || $user->role?->value === 'admin') {
            return $event;
        }

        $ownerId = $user->eventOwner?->id ?? $user->roleId;
        if ($event->eventOwnerId !== $ownerId) {
            throw new AuthorizationException('You are not authorized to view this event.');
        }

        return $event;
    }

    /**
     * Update existing event details, status, or ticket types.
     *
     * @throws AuthorizationException
     */
    public function updateEvent(int $id, UpdateEventDTO $dto, ?UploadedFile $flyerFile, User $user): Event
    {
        $event = Event::findOrFail($id);
        $this->authorizeEventModification($event, $user);

        // Upload flyer if provided
        $flyerUrl = $dto->flyerUrl;
        if ($flyerFile) {
            $path = $flyerFile->store("events/{$id}/flyers", 'r2');
            $flyerUrl = Storage::disk('r2')->url($path);
        }

        $attributes = $dto->toModelAttributes();
        if ($flyerUrl) {
            $attributes['flyerUrl'] = $flyerUrl;
            $attributes['coverImageUrl'] = $flyerUrl;
        }

        return DB::transaction(function () use ($event, $attributes, $dto): Event {
            if (! empty($attributes)) {
                $event->update($attributes);
            }

            // Sync ticket types if provided
            if ($dto->ticketTypes !== null) {
                // If ticket types were sent, sync them
                $event->ticketTypes()->delete();
                foreach ($dto->ticketTypes as $ticketDto) {
                    TicketType::create($ticketDto->toModelAttributes($event->id));
                }
            }

            return $event->fresh(['owner', 'template', 'ticketTypes']);
        });
    }

    /**
     * Delete an event and clean up references.
     *
     * @throws AuthorizationException
     */
    public function deleteEvent(int $id, User $user): bool
    {
        $event = Event::findOrFail($id);
        $this->authorizeEventModification($event, $user);

        return DB::transaction(function () use ($event): bool {
            $ownerId = $event->eventOwnerId;
            $eventId = $event->id;

            $deleted = $event->delete();

            if ($deleted && $ownerId) {
                $owner = EventOwner::find($ownerId);
                if ($owner && is_array($owner->eventIds)) {
                    $updatedIds = array_values(array_filter($owner->eventIds, fn ($eId) => (int) $eId !== (int) $eventId));
                    $owner->update(['eventIds' => $updatedIds]);
                }
            }

            return (bool) $deleted;
        });
    }

    /**
     * Dedicated flyer upload method storing image directly on Cloudflare R2.
     *
     * @throws AuthorizationException
     */
    public function uploadFlyer(int $id, UploadedFile $file, User $user): string
    {
        $event = Event::findOrFail($id);
        $this->authorizeEventModification($event, $user);

        $path = $file->store("events/{$id}/flyers", 'r2');
        $url = Storage::disk('r2')->url($path);

        $event->update([
            'flyerUrl' => $url,
            'coverImageUrl' => $url,
        ]);

        return $url;
    }

    /**
     * Quick status update endpoint.
     *
     * @throws AuthorizationException
     */
    public function updateStatus(int $id, string $status, User $user): Event
    {
        $event = Event::findOrFail($id);
        $this->authorizeEventModification($event, $user);

        $event->update(['status' => $status]);

        return $event->fresh(['owner', 'template', 'ticketTypes']);
    }

    /**
     * List public, published events for visitors.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listPublicEvents(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Event::query()
            ->with(['template', 'ticketTypes'])
            ->where('isPublic', true)
            ->where('status', 'published');

        if (! empty($filters['query'])) {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $searchTerm = '%'.trim((string) $filters['query']).'%';
            $query->where(function ($q) use ($searchTerm, $like): void {
                $q->where('title', $like, $searchTerm)
                    ->orWhere('venueName', $like, $searchTerm)
                    ->orWhere('description', $like, $searchTerm);
            });
        }

        if (! empty($filters['eventCategory'])) {
            $query->where('eventCategory', $filters['eventCategory']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['typeOfEvent'])) {
            $query->where('typeOfEvent', $filters['typeOfEvent']);
        }

        if (! empty($filters['startDate'])) {
            $query->whereDate('startDate', '>=', $filters['startDate']);
        }

        if (isset($filters['isFeatured'])) {
            $query->where('isFeatured', filter_var($filters['isFeatured'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('startDate')->paginate($perPage);
    }

    /**
     * Helper to verify if user is owner of the event or an admin.
     *
     * @throws AuthorizationException
     */
    protected function authorizeEventModification(Event $event, User $user): void
    {
        if ($user->role === UserRole::Admin || $user->role?->value === 'admin') {
            return;
        }

        $ownerId = $user->eventOwner?->id ?? $user->roleId;
        if ($event->eventOwnerId !== $ownerId) {
            throw new AuthorizationException('You are not authorized to modify this event.');
        }
    }
}
