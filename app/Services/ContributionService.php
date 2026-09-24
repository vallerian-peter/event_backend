<?php

namespace App\Services;

use App\DTOs\Contribution\CreateContributionDTO;
use App\DTOs\Contribution\UpdateContributionDTO;
use App\Enums\ContributionStatus;
use App\Enums\UserRole;
use App\Models\Contribution;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ContributionService
{
    /**
     * List contributions with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listContributions(array $filters, ?User $actor = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Contribution::query()->with(['event', 'invitee']);

        if (! empty($filters['eventId'])) {
            $query->where('eventId', $filters['eventId']);
        }

        if (! empty($filters['inviteeId'])) {
            $query->where('inviteeId', $filters['inviteeId']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['division'])) {
            $query->where('division', $filters['division']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transactionId', 'like', "%{$search}%")
                    ->orWhereHas('invitee', function ($iq) use ($search) {
                        $iq->where('fullName', 'like', "%{$search}%")
                            ->orWhere('normalPhone', 'like', "%{$search}%");
                    });
            });
        }

        // Restrict non-admin users to events they own
        if ($actor && $actor->role !== UserRole::Admin) {
            $query->whereHas('event', function ($eq) use ($actor) {
                $eq->where('eventOwnerId', $actor->roleId);
            });
        }

        return $query->latest('updatedAt')->paginate($perPage);
    }

    /**
     * Get a single contribution.
     *
     * @throws AuthorizationException
     */
    public function getContribution(int $id, ?User $actor = null): Contribution
    {
        $contribution = Contribution::with(['event', 'invitee'])->findOrFail($id);

        if ($contribution->event) {
            $this->authorizeAccess($contribution->event, $actor);
        }

        return $contribution;
    }

    /**
     * Create a contribution and auto-sync invitee status.
     *
     * @throws AuthorizationException
     */
    public function createContribution(CreateContributionDTO $dto, ?User $actor = null): Contribution
    {
        $event = Event::findOrFail($dto->eventId);
        $this->authorizeAccess($event, $actor);

        return DB::transaction(function () use ($dto, $event) {
            $status = $dto->status;
            if (! $status) {
                $status = ($dto->paidAmount >= $dto->pledgeAmount && $dto->paidAmount > 0)
                    ? ContributionStatus::Paid
                    : ContributionStatus::Pending;
            }

            /** @var Contribution $contribution */
            $contribution = Contribution::create([
                'eventId' => $event->id,
                'inviteeId' => $dto->inviteeId,
                'pledgeAmount' => (string) $dto->pledgeAmount,
                'paidAmount' => (string) $dto->paidAmount,
                'division' => $dto->division,
                'transactionId' => $dto->transactionId,
                'status' => $status,
            ]);

            if ($dto->inviteeId) {
                $this->syncInviteeContributionStatus($dto->inviteeId);
            }

            return $contribution->load(['event', 'invitee']);
        });
    }

    /**
     * Update an existing contribution and re-sync invitee status.
     *
     * @throws AuthorizationException
     */
    public function updateContribution(int $id, UpdateContributionDTO $dto, ?User $actor = null): Contribution
    {
        $contribution = Contribution::with(['event', 'invitee'])->findOrFail($id);

        if ($contribution->event) {
            $this->authorizeAccess($contribution->event, $actor);
        }

        return DB::transaction(function () use ($contribution, $dto) {
            if (! empty($dto->attributes)) {
                $contribution->update($dto->attributes);
            }

            if ($contribution->inviteeId) {
                $this->syncInviteeContributionStatus($contribution->inviteeId);
            }

            return $contribution->fresh(['event', 'invitee']);
        });
    }

    /**
     * Delete a contribution.
     *
     * @throws AuthorizationException
     */
    public function deleteContribution(int $id, ?User $actor = null): void
    {
        $contribution = Contribution::with('event')->findOrFail($id);

        if ($contribution->event) {
            $this->authorizeAccess($contribution->event, $actor);
        }

        $inviteeId = $contribution->inviteeId;

        $contribution->delete();

        if ($inviteeId) {
            $this->syncInviteeContributionStatus($inviteeId);
        }
    }

    /**
     * Recalculate and synchronize an invitee's contributionStatus from all its contributions.
     */
    public function syncInviteeContributionStatus(int $inviteeId): void
    {
        $invitee = Invitee::find($inviteeId);
        if (! $invitee) {
            return;
        }

        $contributions = Contribution::where('inviteeId', $inviteeId)->get();

        $totalPledged = (float) $contributions->sum(fn ($c) => (float) $c->pledgeAmount);
        $totalPaid = (float) $contributions->sum(fn ($c) => (float) $c->paidAmount);

        $newStatus = ($totalPaid >= $totalPledged && $totalPaid > 0)
            ? ContributionStatus::Paid
            : ContributionStatus::Pending;

        $invitee->update(['contributionStatus' => $newStatus]);
    }

    /**
     * Authorize user access to the event.
     *
     * @throws AuthorizationException
     */
    protected function authorizeAccess(Event $event, ?User $actor): void
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

        throw new AuthorizationException('You are not authorized to manage contributions for this event.');
    }
}
