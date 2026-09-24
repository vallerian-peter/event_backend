<?php

namespace App\Models;

use App\Enums\EventLanguage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $eventOwnerId
 * @property EventLanguage $eventLanguage
 * @property string $timezone
 * @property array $preferredChannels
 * @property array $committeeSettings
 * @property array $messagePreferences
 */
class EventOwnerPreference extends Model
{
    use HasFactory;

    protected $table = 'event_owner_preferences';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'eventOwnerId',
        'eventLanguage',
        'timezone',
        'preferredChannels',
        'committeeSettings',
        'messagePreferences',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eventLanguage' => EventLanguage::class,
            'preferredChannels' => 'array',
            'committeeSettings' => 'array',
            'messagePreferences' => 'array',
        ];
    }

    public function eventOwner(): BelongsTo
    {
        return $this->belongsTo(EventOwner::class, 'eventOwnerId');
    }
}
