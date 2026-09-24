<?php

namespace App\Services;

use App\DTOs\Invitee\CreateInviteeDTO;
use App\DTOs\Invitee\InviteeStatsResponseDTO;
use App\DTOs\Invitee\UpdateInviteeDTO;
use App\Enums\CheckInStatus;
use App\Enums\ContributionStatus;
use App\Enums\EntryType;
use App\Enums\EventDivision;
use App\Enums\InviteeStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateInvitationCardJob;
use App\Models\Contribution;
use App\Models\EntryActivity;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Service class handling Invitee management, QR entry pass issuance,
 * contributions tracking, and attendance verification.
 */
class InviteeService
{
    /**
     * List invitees with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listInvitees(array $filters, ?User $actor = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Invitee::query()
            ->with(['event', 'contributions']);

        // Scope to specific event if provided
        if (! empty($filters['eventId'])) {
            $query->where('eventId', (int) $filters['eventId']);
        }

        // Scope by actor role
        if ($actor && ($actor->role === UserRole::EventOwner || $actor->role?->value === 'event_owner')) {
            $ownerId = $actor->eventOwner?->id ?? $actor->roleId;
            $query->whereHas('event', function ($q) use ($ownerId): void {
                $q->where('eventOwnerId', $ownerId);
            });
        }

        // Filter by status
        if (! empty($filters['status']) || ! empty($filters['inviteStatus'])) {
            $status = $filters['status'] ?? $filters['inviteStatus'];
            $query->where('status', $status);
        }

        // Filter by SMS status
        if (! empty($filters['smsStatus'])) {
            $query->where('smsStatus', $filters['smsStatus']);
        }

        // Filter by Contribution status
        if (! empty($filters['contributionStatus'])) {
            $query->where('contributionStatus', $filters['contributionStatus']);
        }

        // Filter by Division
        if (! empty($filters['division'])) {
            $query->where('division', $filters['division']);
        }

        // Text search
        if (! empty($filters['search']) || ! empty($filters['query'])) {
            $searchTerm = '%'.trim((string) ($filters['search'] ?? $filters['query'])).'%';
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

            $query->where(function ($q) use ($searchTerm, $like): void {
                $q->where('fullName', $like, $searchTerm)
                    ->orWhere('email', $like, $searchTerm)
                    ->orWhere('normalPhone', $like, $searchTerm)
                    ->orWhere('whatsappPhone', $like, $searchTerm)
                    ->orWhere('entryPassCode', $like, $searchTerm);
            });
        }

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Retrieve single invitee by ID with event and contributions.
     *
     * @throws AuthorizationException
     */
    public function getInvitee(int $id, ?User $actor = null): Invitee
    {
        $invitee = Invitee::with(['event', 'contributions', 'entryActivities'])->findOrFail($id);

        $this->authorizeAccess($invitee->event, $actor);

        return $invitee;
    }

    /**
     * Create an invitee, generate QR code, and register contribution.
     *
     * @throws ValidationException
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function createInvitee(CreateInviteeDTO $dto, ?User $actor = null): Invitee
    {
        /** @var Event $event */
        $event = Event::findOrFail($dto->eventId);

        $this->authorizeAccess($event, $actor);

        // 1. Capacity check
        $currentCount = $event->invitees()->count();
        if ($currentCount >= $event->inviteeLimit) {
            throw ValidationException::withMessages([
                'invitee' => ["Invitee limit reached. Increase this event's invitee capacity to continue."],
            ]);
        }

        // 2. Validate division against event division constraints if configured
        $eventDivisionValue = $event->divisions instanceof EventDivision
            ? $event->divisions->value
            : (string) $event->divisions;

        if ($dto->division && ! empty($eventDivisionValue) && $eventDivisionValue !== 'both') {
            if ($eventDivisionValue !== $dto->division) {
                throw ValidationException::withMessages([
                    'division' => ["The selected division '{$dto->division}' is not permitted for this event."],
                ]);
            }
        }

        // 3. Auto-calculate initial contribution status
        $autoStatus = ($dto->paidAmount >= $dto->pledgeAmount && $dto->paidAmount > 0)
            ? ContributionStatus::Paid
            : ContributionStatus::Pending;

        return DB::transaction(function () use ($dto, $event, $autoStatus): Invitee {
            $plainCode = Str::upper(Str::random(9));
            $tokenHash = hash('sha256', $plainCode);

            $attributes = $dto->toModelAttributes();
            $attributes['contributionStatus'] = $autoStatus;
            $attributes['entryPassCode'] = $plainCode;
            $attributes['entryPassTokenHash'] = $tokenHash;

            /** @var Invitee $invitee */
            $invitee = Invitee::create($attributes);

            // Generate and store QR code image
            $this->generateAndStoreQr($invitee);

            // Always create associated Contribution entry
            Contribution::create([
                'inviteeId' => $invitee->id,
                'eventId' => $event->id,
                'pledgeAmount' => (string) $dto->pledgeAmount,
                'paidAmount' => (string) $dto->paidAmount,
                'division' => $dto->division,
                'status' => $autoStatus,
            ]);

            // If event has an invitation template, dispatch background generation job
            if ($event->templateId) {
                GenerateInvitationCardJob::dispatch($invitee->id, $event->templateId);
            }

            return $invitee->load(['event', 'contributions']);
        });
    }

    /**
     * Update an existing invitee and sync contribution amounts.
     *
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function updateInvitee(int $id, UpdateInviteeDTO $dto, ?User $actor = null): Invitee
    {
        $invitee = Invitee::with(['event', 'contributions'])->findOrFail($id);

        $this->authorizeAccess($invitee->event, $actor);

        return DB::transaction(function () use ($invitee, $dto): Invitee {
            if (! empty($dto->attributes)) {
                $invitee->update($dto->attributes);
            }

            // Sync contribution if amounts or division provided
            if ($dto->pledgeAmount !== null || $dto->paidAmount !== null || $dto->division !== null) {
                $latestContribution = $invitee->contributions()->latest('updatedAt')->first();

                $newPledge = $dto->pledgeAmount ?? ($latestContribution ? (float) $latestContribution->pledgeAmount : 0.0);
                $newPaid = $dto->paidAmount ?? ($latestContribution ? (float) $latestContribution->paidAmount : 0.0);
                $newDivision = $dto->division ?? $latestContribution?->division ?? $invitee->division?->value;

                $newStatus = ($newPaid >= $newPledge && $newPaid > 0)
                    ? ContributionStatus::Paid
                    : ContributionStatus::Pending;

                if ($latestContribution) {
                    $latestContribution->update([
                        'pledgeAmount' => (string) $newPledge,
                        'paidAmount' => (string) $newPaid,
                        'division' => $newDivision,
                        'status' => $newStatus,
                    ]);
                } else {
                    Contribution::create([
                        'inviteeId' => $invitee->id,
                        'eventId' => $invitee->eventId,
                        'pledgeAmount' => (string) $newPledge,
                        'paidAmount' => (string) $newPaid,
                        'division' => $newDivision,
                        'status' => $newStatus,
                    ]);
                }

                $invitee->update(['contributionStatus' => $newStatus]);
            }

            return $invitee->fresh(['event', 'contributions']);
        });
    }

    /**
     * Delete an invitee, remove QR assets, and clean up.
     *
     * @throws AuthorizationException
     */
    public function deleteInvitee(int $id, ?User $actor = null): void
    {
        $invitee = Invitee::with('event')->findOrFail($id);

        $this->authorizeAccess($invitee->event, $actor);

        // Delete QR image from Cloudflare R2
        $qrPath = "events/{$invitee->eventId}/entry-passes/{$invitee->id}.png";
        Storage::disk('r2')->delete($qrPath);

        $invitee->delete();
    }

    /**
     * Bulk create invitees for an event with capacity validation.
     *
     * @param  array<int, array<string, mixed>>  $inviteesData
     * @return Invitee[]
     *
     * @throws ValidationException
     * @throws AuthorizationException
     * @throws \Throwable
     */
    public function bulkCreateInvitees(int $eventId, array $inviteesData, ?User $actor = null): array
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $this->authorizeAccess($event, $actor);

        $currentCount = $event->invitees()->count();
        $batchSize = count($inviteesData);

        if ($currentCount + $batchSize > $event->inviteeLimit) {
            throw ValidationException::withMessages([
                'invitee' => ["Invitee limit reached. Adding {$batchSize} guests would exceed the event capacity of {$event->inviteeLimit}."],
            ]);
        }

        return DB::transaction(function () use ($event, $inviteesData): array {
            $created = [];

            foreach ($inviteesData as $raw) {
                $dto = CreateInviteeDTO::fromArray($raw, $event->id);

                $autoStatus = ($dto->paidAmount >= $dto->pledgeAmount && $dto->paidAmount > 0)
                    ? ContributionStatus::Paid
                    : ContributionStatus::Pending;

                $plainCode = Str::upper(Str::random(9));
                $tokenHash = hash('sha256', $plainCode);

                $attributes = $dto->toModelAttributes();
                $attributes['contributionStatus'] = $autoStatus;
                $attributes['entryPassCode'] = $plainCode;
                $attributes['entryPassTokenHash'] = $tokenHash;

                /** @var Invitee $invitee */
                $invitee = Invitee::create($attributes);

                $this->generateAndStoreQr($invitee);

                Contribution::create([
                    'inviteeId' => $invitee->id,
                    'eventId' => $event->id,
                    'pledgeAmount' => (string) $dto->pledgeAmount,
                    'paidAmount' => (string) $dto->paidAmount,
                    'division' => $dto->division,
                    'status' => $autoStatus,
                ]);

                if ($event->templateId) {
                    GenerateInvitationCardJob::dispatch($invitee->id, $event->templateId);
                }

                $created[] = $invitee->load(['event', 'contributions']);
            }

            return $created;
        });
    }

    /**
     * Bulk update invitee statuses.
     *
     * @param  int[]  $inviteeIds
     *
     * @throws AuthorizationException
     */
    public function bulkUpdateStatus(int $eventId, array $inviteeIds, string $status, ?User $actor = null): int
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $this->authorizeAccess($event, $actor);

        $query = Invitee::where('eventId', $eventId);

        if (! empty($inviteeIds)) {
            $query->whereIn('id', $inviteeIds);
        }

        return $query->update(['status' => $status]);
    }

    /**
     * Compute aggregated attendee and contribution stats for an event.
     *
     * @throws AuthorizationException
     */
    public function getEventStats(int $eventId, ?User $actor = null): InviteeStatsResponseDTO
    {
        /** @var Event $event */
        $event = Event::findOrFail($eventId);

        $this->authorizeAccess($event, $actor);

        $allInvitees = Invitee::where('eventId', $eventId)->get();

        $contributions = Contribution::where('eventId', $eventId)->get();
        $totalPledged = $contributions->sum(fn (Contribution $c) => (float) $c->pledgeAmount);
        $totalPaid = $contributions->sum(fn (Contribution $c) => (float) $c->paidAmount);

        return new InviteeStatsResponseDTO(
            total: $allInvitees->count(),
            active: $allInvitees->where('status', InviteeStatus::Active)->count(),
            confirmed: $allInvitees->where('status', InviteeStatus::Confirmed)->count(),
            pending: $allInvitees->where('status', InviteeStatus::Pending)->count(),
            declined: $allInvitees->where('status', InviteeStatus::Declined)->count(),
            attended: $allInvitees->where('status', InviteeStatus::Attended)->count(),
            totalPledged: (float) $totalPledged,
            totalPaid: (float) $totalPaid,
            smsDelivered: $allInvitees->where('smsStatus', 'delivered')->count(),
            smsPending: $allInvitees->where('smsStatus', 'pending')->count(),
            smsFailed: $allInvitees->where('smsStatus', 'failed')->count(),
        );
    }

    /**
     * Dedicated QR preview payload matching event-management-system API contract.
     *
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function getQrPreview(int $id, ?User $actor = null): array
    {
        $invitee = Invitee::with('event')->findOrFail($id);

        $this->authorizeAccess($invitee->event, $actor);

        $qrPath = "events/{$invitee->eventId}/entry-passes/{$invitee->id}.png";

        // If not already in storage, generate on demand
        if (! Storage::disk('r2')->exists($qrPath)) {
            $this->generateAndStoreQr($invitee);
        }

        $qrImageUrl = Storage::disk('r2')->url($qrPath);

        return [
            'success' => true,
            'available' => true,
            'mode' => 'stored_token',
            'inviteeId' => $invitee->id,
            'fullName' => $invitee->fullName,
            'code' => $invitee->entryPassCode,
            'qrImageUrl' => $qrImageUrl,
        ];
    }

    /**
     * Check-in an invitee at the gate and log EntryActivity.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function checkIn(int $id, array $details = [], ?User $actor = null): array
    {
        $invitee = Invitee::with('event')->findOrFail($id);

        // Check if already consumed
        if ($invitee->entryPassConsumedAt !== null) {
            // Log entry attempt failure
            EntryActivity::create([
                'eventId' => $invitee->eventId,
                'entryType' => EntryType::Invitee,
                'inviteeId' => $invitee->id,
                'scannedCode' => $details['scannedCode'] ?? $invitee->entryPassCode,
                'status' => CheckInStatus::Duplicate,
                'failureReason' => 'Entry pass has already been used.',
                'gateName' => $details['gateName'] ?? null,
                'gateKeeperId' => $details['gateKeeperId'] ?? null,
                'deviceInfo' => $details['deviceInfo'] ?? null,
                'isOfflineSync' => (bool) ($details['isOfflineSync'] ?? false),
                'scannedAt' => now(),
            ]);

            throw ValidationException::withMessages([
                'entryPass' => ["This entry pass was already used at {$invitee->entryPassConsumedAt->format('Y-m-d H:i:s')}."],
            ]);
        }

        return DB::transaction(function () use ($invitee, $details, $actor): array {
            $scannedByUuid = $actor?->uuid ?? null;
            $invitee->markAsConsumed($scannedByUuid);

            $activity = EntryActivity::create([
                'eventId' => $invitee->eventId,
                'entryType' => EntryType::Invitee,
                'inviteeId' => $invitee->id,
                'scannedCode' => $details['scannedCode'] ?? $invitee->entryPassCode,
                'status' => CheckInStatus::Success,
                'gateName' => $details['gateName'] ?? 'Main Entrance',
                'gateKeeperId' => $details['gateKeeperId'] ?? null,
                'deviceInfo' => $details['deviceInfo'] ?? null,
                'isOfflineSync' => (bool) ($details['isOfflineSync'] ?? false),
                'scannedAt' => now(),
            ]);

            return [
                'success' => true,
                'message' => "Welcome, {$invitee->fullName}! Entry approved.",
                'inviteeId' => $invitee->id,
                'fullName' => $invitee->fullName,
                'status' => $invitee->status->value ?? (string) $invitee->status,
                'consumedAt' => $invitee->entryPassConsumedAt?->toIso8601String(),
                'activityId' => $activity->id,
            ];
        });
    }

    /**
     * Generate PNG QR code and upload to Cloudflare R2 disk.
     */
    public function generateAndStoreQr(Invitee $invitee): string
    {
        $code = $invitee->entryPassCode;
        $qrPath = "events/{$invitee->eventId}/entry-passes/{$invitee->id}.png";

        try {
            $png = @QrCode::format('png')->size(300)->errorCorrection('H')->generate($code);
            Storage::disk('r2')->put($qrPath, (string) $png, 'public');
        } catch (\Throwable $e) {
            Log::warning("Could not generate entry pass QR for invitee #{$invitee->id}: {$e->getMessage()}");
        }

        return Storage::disk('r2')->url($qrPath);
    }

    /**
     * Authorize whether actor has access to this event's invitees.
     *
     * @throws AuthorizationException
     */
    protected function authorizeAccess(Event $event, ?User $actor): void
    {
        if (! $actor) {
            return;
        }

        if ($actor->role === UserRole::Admin || $actor->role?->value === 'admin') {
            return;
        }

        if ($actor->role === UserRole::EventOwner || $actor->role?->value === 'event_owner') {
            $ownerId = $actor->eventOwner?->id ?? $actor->roleId;
            if ($event->eventOwnerId !== $ownerId) {
                throw new AuthorizationException('You are not authorized to access invitees for this event.');
            }
        }
    }
}
