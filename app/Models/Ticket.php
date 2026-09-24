<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $orderId
 * @property int $ticketTypeId
 * @property int $eventId
 * @property string $ticketNumber
 * @property string $holderName
 * @property string|null $holderEmail
 * @property string|null $holderPhone
 * @property string $qrToken
 * @property string $tokenHash
 * @property TicketStatus $status
 * @property string|null $checkedInAt
 * @property int|null $checkedInByGateKeeperId
 */
class Ticket extends Model
{
    use HasFactory;

    protected $table = 'tickets';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $attributes = [
        'status' => TicketStatus::Valid,
    ];

    protected $fillable = [
        'orderId',
        'ticketTypeId',
        'eventId',
        'ticketNumber',
        'holderName',
        'holderEmail',
        'holderPhone',
        'qrToken',
        'tokenHash',
        'status',
        'checkedInAt',
        'checkedInByGateKeeperId',
    ];

    /**
     * Boot model to generate ticketNumber, qrToken, and tokenHash.
     */
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            if (empty($ticket->ticketNumber)) {
                $ticket->ticketNumber = 'TKT-'.Str::upper(Str::random(10));
            }
            if (empty($ticket->qrToken)) {
                $ticket->qrToken = Str::random(32);
            }
            if (empty($ticket->tokenHash)) {
                $ticket->tokenHash = hash('sha256', $ticket->qrToken);
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
            'status' => TicketStatus::class,
            'checkedInAt' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'orderId');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticketTypeId');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }

    public function gateKeeper(): BelongsTo
    {
        return $this->belongsTo(GateKeeper::class, 'checkedInByGateKeeperId');
    }

    public function entryActivities(): HasMany
    {
        return $this->hasMany(EntryActivity::class, 'ticketId');
    }

    public function markAsUsed(?int $gateKeeperId = null): void
    {
        $this->update([
            'status' => TicketStatus::Used,
            'checkedInAt' => now(),
            'checkedInByGateKeeperId' => $gateKeeperId,
        ]);
    }
}
