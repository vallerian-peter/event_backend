<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $userId
 * @property string|null $username
 * @property string $firstName
 * @property string|null $middleName
 * @property string $lastName
 * @property string|null $email
 * @property string|null $phone
 * @property array $eventIds
 */
class EventOwner extends Model
{
    use HasFactory;

    protected $table = 'eventOwners';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'userId',
        'username',
        'firstName',
        'middleName',
        'lastName',
        'email',
        'phone',
        'eventIds',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eventIds' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(EventOwnerPreference::class, 'eventOwnerId');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EventsPayment::class, 'eventOwnerId');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'eventOwnerId');
    }
}
