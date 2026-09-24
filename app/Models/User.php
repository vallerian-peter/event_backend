<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property string|null $phone
 * @property string $password
 * @property UserRole $role
 * @property int|null $roleId
 * @property UserStatus $status
 * @property string|null $avatarUrl
 * @method \Laravel\Sanctum\PersonalAccessToken|null currentAccessToken()
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'username',
        'email',
        'phone',
        'password',
        'role',
        'roleId',
        'status',
        'avatarUrl',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Boot the model to auto-generate UUID.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Related event owner profile.
     */
    public function eventOwner(): HasOne
    {
        return $this->hasOne(EventOwner::class, 'userId');
    }

    /**
     * Related gatekeeper profile.
     */
    public function gateKeeper(): HasOne
    {
        return $this->hasOne(GateKeeper::class, 'userId');
    }

    /**
     * Related system user profile.
     */
    public function systemUser(): HasOne
    {
        return $this->hasOne(SystemUser::class, 'userId');
    }
}
