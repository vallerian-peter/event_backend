<?php

namespace App\Models;

use App\Enums\CheckInStatus;
use App\Enums\EntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $eventId
 * @property EntryType $entryType
 * @property int|null $inviteeId
 * @property int|null $ticketId
 * @property int|null $gateKeeperId
 * @property string $scannedCode
 * @property CheckInStatus $status
 * @property string|null $failureReason
 * @property string|null $gateName
 * @property array|null $deviceInfo
 * @property bool $isOfflineSync
 * @property string $scannedAt
 */
class EntryActivity extends Model
{
    use HasFactory;

    protected $table = 'entry_activities';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'eventId',
        'entryType',
        'inviteeId',
        'ticketId',
        'gateKeeperId',
        'scannedCode',
        'status',
        'failureReason',
        'gateName',
        'deviceInfo',
        'isOfflineSync',
        'scannedAt',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entryType' => EntryType::class,
            'status' => CheckInStatus::class,
            'isOfflineSync' => 'boolean',
            'deviceInfo' => 'array',
            'scannedAt' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(Invitee::class, 'inviteeId');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticketId');
    }

    public function gateKeeper(): BelongsTo
    {
        return $this->belongsTo(GateKeeper::class, 'gateKeeperId');
    }
}
