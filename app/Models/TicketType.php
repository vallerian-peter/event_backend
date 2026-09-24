<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $eventId
 * @property string $name
 * @property string|null $description
 * @property float $price
 * @property string $currency
 * @property int $quantityTotal
 * @property int $quantitySold
 * @property int $minPerOrder
 * @property int $maxPerOrder
 * @property string|null $salesStartAt
 * @property string|null $salesEndAt
 * @property bool $isActive
 */
class TicketType extends Model
{
    use HasFactory;

    protected $table = 'ticket_types';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'eventId',
        'name',
        'description',
        'price',
        'currency',
        'quantityTotal',
        'quantitySold',
        'minPerOrder',
        'maxPerOrder',
        'salesStartAt',
        'salesEndAt',
        'isActive',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantityTotal' => 'integer',
            'quantitySold' => 'integer',
            'minPerOrder' => 'integer',
            'maxPerOrder' => 'integer',
            'isActive' => 'boolean',
            'salesStartAt' => 'datetime',
            'salesEndAt' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticketTypeId');
    }

    public function availableQuantity(): int
    {
        return max(0, $this->quantityTotal - $this->quantitySold);
    }

    public function isAvailable(): bool
    {
        if (! $this->isActive || $this->availableQuantity() <= 0) {
            return false;
        }

        $now = now();
        if ($this->salesStartAt && $now->lt($this->salesStartAt)) {
            return false;
        }
        if ($this->salesEndAt && $now->gt($this->salesEndAt)) {
            return false;
        }

        return true;
    }
}
