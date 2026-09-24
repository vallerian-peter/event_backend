<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $eventId
 * @property int $subscriptionId
 * @property string $featureKey
 * @property int $grantedUnits
 * @property int $reservedUnits
 * @property int $consumedUnits
 * @property array $metadata
 */
class EventFeatureBalance extends Model
{
    use HasFactory;

    protected $table = 'event_feature_balances';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'eventId',
        'subscriptionId',
        'featureKey',
        'grantedUnits',
        'reservedUnits',
        'consumedUnits',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grantedUnits' => 'integer',
            'reservedUnits' => 'integer',
            'consumedUnits' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(EventPackageSubscription::class, 'subscriptionId');
    }

    public function remainingUnits(): int
    {
        return max(0, $this->grantedUnits - $this->reservedUnits - $this->consumedUnits);
    }

    public function canConsume(int $units = 1): bool
    {
        return $this->remainingUnits() >= $units;
    }
}
