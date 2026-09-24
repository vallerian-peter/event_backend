<?php

namespace App\Services;

use App\Enums\CheckInStatus;
use App\Enums\EntryType;
use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\EntryActivity;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketingService
{
    /**
     * List ticket types for an event.
     *
     * @return Collection<int, TicketType>
     */
    public function listTicketTypes(int $eventId, bool $activeOnly = false, ?User $actor = null): Collection
    {
        $event = Event::findOrFail($eventId);

        if (! $activeOnly) {
            $this->authorizeOwnerOrAdmin($event, $actor);
        }

        $query = TicketType::where('eventId', $eventId);

        if ($activeOnly) {
            $query->where('isActive', true);
        }

        return $query->latest('createdAt')->get();
    }

    /**
     * Create a ticket type for an event.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTicketType(int $eventId, array $data, ?User $actor = null): TicketType
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeOwnerOrAdmin($event, $actor);

        $data['eventId'] = $event->id;
        $data['quantitySold'] = 0;
        $data['currency'] = $data['currency'] ?? 'TZS';
        $data['isActive'] = $data['isActive'] ?? true;

        return TicketType::create($data);
    }

    /**
     * Get a specific ticket type.
     */
    public function getTicketType(int $id): TicketType
    {
        return TicketType::with('event')->findOrFail($id);
    }

    /**
     * Update a ticket type.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTicketType(int $id, array $data, ?User $actor = null): TicketType
    {
        $ticketType = TicketType::findOrFail($id);
        $this->authorizeOwnerOrAdmin($ticketType->event, $actor);

        $ticketType->update($data);

        return $ticketType->fresh();
    }

    /**
     * Delete or deactivate a ticket type.
     */
    public function deleteTicketType(int $id, ?User $actor = null): void
    {
        $ticketType = TicketType::findOrFail($id);
        $this->authorizeOwnerOrAdmin($ticketType->event, $actor);

        if ($ticketType->quantitySold > 0) {
            $ticketType->update(['isActive' => false]);

            return;
        }

        $ticketType->delete();
    }

    /**
     * Discover public events catalog.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listPublicEvents(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Event::where('isPublic', true)
            ->where('status', '!=', 'cancelled');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('venueName', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['typeOfEvent'])) {
            $query->where('typeOfEvent', $filters['typeOfEvent']);
        }

        return $query->with(['ticketTypes' => function ($q) {
            $q->where('isActive', true);
        }])
            ->latest('startDate')
            ->paginate($perPage);
    }

    /**
     * Get public event details and active ticket types by slug.
     *
     * @return array<string, mixed>
     */
    public function getPublicEventBySlug(string $slug): array
    {
        /** @var Event $event */
        $event = Event::where('slug', $slug)
            ->where('isPublic', true)
            ->firstOrFail();

        $ticketTypes = TicketType::where('eventId', $event->id)
            ->where('isActive', true)
            ->get();

        return [
            'event' => $event,
            'ticketTypes' => $ticketTypes,
        ];
    }

    /**
     * Public ticket checkout and purchase.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function checkout(int $eventId, array $data, ?User $actor = null): array
    {
        $event = Event::findOrFail($eventId);

        /** @var TicketType $ticketType */
        $ticketType = TicketType::where('id', $data['ticketTypeId'])
            ->where('eventId', $eventId)
            ->firstOrFail();

        $quantity = (int) $data['quantity'];

        if (! $ticketType->isAvailable()) {
            throw ValidationException::withMessages([
                'ticketTypeId' => ['This ticket type is currently sold out or unavailable.'],
            ]);
        }

        if ($ticketType->availableQuantity() < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ["Only {$ticketType->availableQuantity()} tickets available for this type."],
            ]);
        }

        if ($quantity < ($ticketType->minPerOrder ?? 1)) {
            throw ValidationException::withMessages([
                'quantity' => ["Minimum order quantity is {$ticketType->minPerOrder}."],
            ]);
        }

        if ($quantity > ($ticketType->maxPerOrder ?? 10)) {
            throw ValidationException::withMessages([
                'quantity' => ["Maximum order quantity is {$ticketType->maxPerOrder}."],
            ]);
        }

        $totalAmount = (float) $ticketType->price * $quantity;

        return DB::transaction(function () use ($event, $ticketType, $quantity, $totalAmount, $data, $actor) {
            /** @var TicketOrder $order */
            $order = TicketOrder::create([
                'eventId' => $event->id,
                'userId' => $actor?->id,
                'customerName' => $data['customerName'],
                'customerEmail' => $data['customerEmail'],
                'customerPhone' => $data['customerPhone'],
                'totalAmount' => $totalAmount,
                'paidAmount' => $totalAmount,
                'currency' => $ticketType->currency,
                'paymentMethod' => $data['paymentMethod'] ?? 'azampay',
                'transactionId' => 'TXN-'.strtoupper(uniqid()),
                'status' => OrderStatus::Paid,
            ]);

            $ticketType->increment('quantitySold', $quantity);

            $tickets = [];
            $attendees = $data['attendees'] ?? [];

            for ($i = 0; $i < $quantity; $i++) {
                $holderName = $attendees[$i]['name'] ?? ($data['customerName'].($quantity > 1 ? ' #'.($i + 1) : ''));
                $holderEmail = $attendees[$i]['email'] ?? $data['customerEmail'];
                $holderPhone = $attendees[$i]['phone'] ?? $data['customerPhone'];

                $ticket = Ticket::create([
                    'orderId' => $order->id,
                    'ticketTypeId' => $ticketType->id,
                    'eventId' => $event->id,
                    'holderName' => $holderName,
                    'holderEmail' => $holderEmail,
                    'holderPhone' => $holderPhone,
                    'status' => TicketStatus::Valid,
                ]);

                $tickets[] = $ticket;
            }

            return [
                'order' => $order->fresh(),
                'tickets' => $tickets,
                'paymentStatus' => 'COMPLETED',
            ];
        });
    }

    /**
     * Verify ticket validity by code or token.
     *
     * @throws ValidationException
     */
    public function verifyTicket(string $code): Ticket
    {
        $code = trim($code);

        /** @var Ticket|null $ticket */
        $ticket = Ticket::with(['event', 'ticketType', 'order'])
            ->where('qrToken', $code)
            ->orWhere('ticketNumber', $code)
            ->orWhere('tokenHash', hash('sha256', $code))
            ->first();

        if (! $ticket) {
            throw ValidationException::withMessages([
                'code' => ['Invalid ticket or QR code.'],
            ]);
        }

        return $ticket;
    }

    /**
     * Check in a ticket at gate entrance.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function checkInTicket(string $code, ?int $gateKeeperId = null, ?string $gateName = null): array
    {
        $ticket = $this->verifyTicket($code);

        if ($ticket->status === TicketStatus::Used) {
            // Log duplicate scan
            EntryActivity::create([
                'eventId' => $ticket->eventId,
                'entryType' => EntryType::Ticket,
                'ticketId' => $ticket->id,
                'gateKeeperId' => $gateKeeperId,
                'scannedCode' => $code,
                'status' => CheckInStatus::Duplicate,
                'failureReason' => 'Ticket has already been used.',
                'gateName' => $gateName,
                'scannedAt' => now(),
            ]);

            throw ValidationException::withMessages([
                'code' => ['Ticket already used on '.($ticket->checkedInAt?->toDateTimeString() ?? 'previously').'.'],
            ]);
        }

        if ($ticket->status !== TicketStatus::Valid) {
            EntryActivity::create([
                'eventId' => $ticket->eventId,
                'entryType' => EntryType::Ticket,
                'ticketId' => $ticket->id,
                'gateKeeperId' => $gateKeeperId,
                'scannedCode' => $code,
                'status' => CheckInStatus::Invalid,
                'failureReason' => "Ticket status is {$ticket->status->value}.",
                'gateName' => $gateName,
                'scannedAt' => now(),
            ]);

            throw ValidationException::withMessages([
                'code' => ["Ticket is not valid (status: {$ticket->status->value})."],
            ]);
        }

        $ticket->markAsUsed($gateKeeperId);

        $activity = EntryActivity::create([
            'eventId' => $ticket->eventId,
            'entryType' => EntryType::Ticket,
            'ticketId' => $ticket->id,
            'gateKeeperId' => $gateKeeperId,
            'scannedCode' => $code,
            'status' => CheckInStatus::Success,
            'gateName' => $gateName,
            'scannedAt' => now(),
        ]);

        return [
            'ticket' => $ticket->fresh(['ticketType', 'event']),
            'activity' => $activity,
            'message' => 'Ticket checked in successfully.',
        ];
    }

    /**
     * List ticket orders for an event.
     *
     * @return Collection<int, TicketOrder>
     */
    public function listOrders(int $eventId, ?User $actor = null): Collection
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeOwnerOrAdmin($event, $actor);

        return TicketOrder::with(['tickets.ticketType'])
            ->where('eventId', $eventId)
            ->latest('createdAt')
            ->get();
    }

    /**
     * Get ticket order by order number.
     */
    public function getOrderByNumber(string $orderNumber): TicketOrder
    {
        return TicketOrder::with(['tickets.ticketType', 'event'])
            ->where('orderNumber', $orderNumber)
            ->firstOrFail();
    }

    /**
     * Authorize event owner or admin.
     *
     * @throws AuthorizationException
     */
    protected function authorizeOwnerOrAdmin(Event $event, ?User $actor): void
    {
        if (! $actor) {
            return;
        }

        if ($actor->role === UserRole::Admin) {
            return;
        }

        if ($actor->role === UserRole::EventOwner && (int) $actor->roleId === (int) $event->eventOwnerId) {
            return;
        }

        throw new AuthorizationException('You are not authorized to manage ticketing for this event.');
    }
}
