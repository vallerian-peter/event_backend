<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $userId
 * @property string|null $username
 * @property string $fullName
 * @property string|null $phone
 * @property string|null $email
 * @property array $eventIds
 */
class GateKeeper extends Model
{
    use HasFactory;

    protected $table = 'gateKeepers';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'userId',
        'username',
        'fullName',
        'phone',
        'email',
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

    public function entryActivities(): HasMany
    {
        return $this->hasMany(EntryActivity::class, 'gateKeeperId');
    }

    public function checkedInTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'checkedInByGateKeeperId');
    }
}
