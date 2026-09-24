<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\EntryActivity;
use App\Models\Event;
use App\Models\GateKeeper;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GateKeeperService
{
    /**
     * List gatekeepers assigned to an event.
     *
     * @return Collection<int, GateKeeper>
     */
    public function listGateKeepers(int $eventId, ?User $actor = null): Collection
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeAccess($event, $actor);

        return GateKeeper::whereJsonContains('eventIds', $eventId)
            ->latest('createdAt')
            ->get();
    }

    /**
     * Create a gatekeeper and grant event access credentials.
     *
     * @param  array<string, mixed>  $data
     * @return array{gatekeeper: GateKeeper, accessPin: string}
     */
    public function createGateKeeper(int $eventId, array $data, ?User $actor = null): array
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeAccess($event, $actor);

        $pin = $data['pin'] ?? (string) rand(100000, 999999);
        $phone = $data['phone'];
        $username = 'gate_'.Str::lower(Str::random(6));

        return DB::transaction(function () use ($event, $data, $pin, $phone, $username) {
            /** @var GateKeeper $gateKeeper */
            $gateKeeper = GateKeeper::create([
                'fullName' => $data['fullName'],
                'phone' => $phone,
                'email' => $data['email'] ?? null,
                'username' => $username,
                'eventIds' => [$event->id],
            ]);

            /** @var User $user */
            $user = User::create([
                'name' => $data['fullName'],
                'username' => $username,
                'email' => $data['email'] ?? ($username.'@gatekeeper.local'),
                'phone' => $phone,
                'password' => Hash::make($pin),
                'role' => UserRole::GateKeeper,
                'roleId' => $gateKeeper->id,
                'status' => UserStatus::Active,
            ]);

            $gateKeeper->update(['userId' => $user->id]);

            return [
                'gatekeeper' => $gateKeeper->fresh(),
                'accessPin' => $pin,
            ];
        });
    }

    /**
     * Authenticate gatekeeper via PIN.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function authenticate(string $identifier, string $pin): array
    {
        /** @var User|null $user */
        $user = User::where('phone', $identifier)
            ->orWhere('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $user || ! Hash::check($pin, $user->password)) {
            throw ValidationException::withMessages([
                'pin' => ['Invalid gatekeeper access credentials or PIN.'],
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'account' => ['This gatekeeper account is inactive.'],
            ]);
        }

        $gateKeeper = GateKeeper::find($user->roleId);

        $token = $user->createToken('gatekeeper-scanner', ['gatekeeper:scan'])->plainTextToken;

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'phone' => $user->phone,
                'role' => $user->role->value,
            ],
            'gateKeeper' => $gateKeeper,
            'assignedEventIds' => $gateKeeper?->eventIds ?? [],
        ];
    }

    /**
     * Get attendee manifest for gate scanning / offline sync.
     *
     * @return array<string, mixed>
     */
    public function getEventManifest(int $eventId, ?string $search = null): array
    {
        $event = Event::findOrFail($eventId);

        $query = Invitee::where('eventId', $eventId)
            ->where('status', '!=', 'declined');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('fullName', 'like', "%{$search}%")
                    ->orWhere('normalPhone', 'like', "%{$search}%")
                    ->orWhere('entryPassCode', 'like', "%{$search}%");
            });
        }

        $invitees = $query->select([
            'id',
            'eventId',
            'fullName',
            'title',
            'normalPhone',
            'whatsappPhone',
            'status',
            'division',
            'contributionStatus',
            'entryPassCode',
            'entryPassConsumedAt',
        ])->get();

        return [
            'eventId' => $event->id,
            'eventTitle' => $event->title,
            'totalManifest' => $invitees->count(),
            'invitees' => $invitees,
        ];
    }

    /**
     * Get live entry activities / scans for an event.
     */
    public function getGateActivities(int $eventId, ?User $actor = null, int $perPage = 50): LengthAwarePaginator
    {
        $event = Event::findOrFail($eventId);
        $this->authorizeAccess($event, $actor);

        return EntryActivity::with('invitee')
            ->where('eventId', $eventId)
            ->latest('createdAt')
            ->paginate($perPage);
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

        if ($actor->role === UserRole::GateKeeper) {
            $gk = GateKeeper::find($actor->roleId);
            if ($gk && in_array($event->id, $gk->eventIds ?? [])) {
                return;
            }
        }

        throw new AuthorizationException('You are not authorized to view or manage gate activities for this event.');
    }
}
