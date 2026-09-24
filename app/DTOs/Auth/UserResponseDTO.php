<?php

namespace App\DTOs\Auth;

use App\Models\User;

/**
 * Data Transfer Object for formatted user response.
 */
class UserResponseDTO
{
    /**
     * @param  array<string, mixed>|null  $profile
     */
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $name,
        public readonly ?string $username,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $role,
        public readonly ?int $roleId,
        public readonly string $status,
        public readonly ?string $avatarUrl,
        public readonly ?array $profile = null,
        public readonly ?string $createdAt = null,
    ) {}

    /**
     * Create UserResponseDTO from Eloquent User model.
     */
    public static function fromModel(User $user): self
    {
        $profileData = null;

        // Load specific profile based on user role
        if ($user->role?->value === 'event_owner' || $user->role === 'event_owner') {
            $owner = $user->relationLoaded('eventOwner') ? $user->eventOwner : $user->eventOwner()->first();
            if ($owner) {
                $profileData = [
                    'id' => $owner->id,
                    'firstName' => $owner->firstName,
                    'middleName' => $owner->middleName,
                    'lastName' => $owner->lastName,
                    'phone' => $owner->phone,
                    'email' => $owner->email,
                    'eventIds' => $owner->eventIds ?? [],
                ];
            }
        } elseif ($user->role?->value === 'gate_keeper' || $user->role === 'gate_keeper') {
            $gk = $user->relationLoaded('gateKeeper') ? $user->gateKeeper : $user->gateKeeper()->first();
            if ($gk) {
                $profileData = [
                    'id' => $gk->id,
                    'fullName' => $gk->fullName,
                    'phone' => $gk->phone,
                    'email' => $gk->email,
                    'eventIds' => $gk->eventIds ?? [],
                ];
            }
        } elseif ($user->role?->value === 'admin' || $user->role === 'admin') {
            $admin = $user->relationLoaded('systemUser') ? $user->systemUser : $user->systemUser()->first();
            if ($admin) {
                $profileData = [
                    'id' => $admin->id,
                    'firstName' => $admin->firstName,
                    'middleName' => $admin->middleName,
                    'lastName' => $admin->lastName,
                    'phone' => $admin->phone,
                    'email' => $admin->email,
                ];
            }
        }

        return new self(
            id: $user->id,
            uuid: $user->uuid,
            name: $user->name,
            username: $user->username,
            email: $user->email,
            phone: $user->phone,
            role: is_string($user->role) ? $user->role : $user->role->value,
            roleId: $user->roleId,
            status: is_string($user->status) ? $user->status : $user->status->value,
            avatarUrl: $user->avatarUrl,
            profile: $profileData,
            createdAt: $user->createdAt ? $user->createdAt->toISOString() : null,
        );
    }

    /**
     * Convert DTO to standard associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'roleId' => $this->roleId,
            'status' => $this->status,
            'avatarUrl' => $this->avatarUrl,
            'profile' => $this->profile,
            'createdAt' => $this->createdAt,
        ];
    }
}
