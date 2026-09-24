<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $batchId
 * @property int $eventId
 * @property int|null $inviteeId
 * @property string $featureKey
 * @property string $messagePurpose
 * @property MessageChannel $channel
 * @property int $units
 * @property MessageStatus $status
 * @property string|null $provider
 * @property string|null $providerReference
 * @property string $idempotencyKey
 * @property string|null $errorMessage
 * @property array $payload
 * @property string $reservedAt
 * @property string|null $sentAt
 * @property string|null $deliveredAt
 * @property string|null $failedAt
 */
class MessageRecord extends Model
{
    use HasFactory;

    protected $table = 'message_records';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'batchId',
        'eventId',
        'inviteeId',
        'featureKey',
        'messagePurpose',
        'channel',
        'units',
        'status',
        'provider',
        'providerReference',
        'idempotencyKey',
        'errorMessage',
        'payload',
        'reservedAt',
        'sentAt',
        'deliveredAt',
        'failedAt',
    ];

    /**
     * Boot model to auto-generate idempotencyKey.
     */
    protected static function booted(): void
    {
        static::creating(function (MessageRecord $record): void {
            if (empty($record->idempotencyKey)) {
                $record->idempotencyKey = (string) Str::uuid();
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
            'channel' => MessageChannel::class,
            'status' => MessageStatus::class,
            'units' => 'integer',
            'payload' => 'array',
            'reservedAt' => 'datetime',
            'sentAt' => 'datetime',
            'deliveredAt' => 'datetime',
            'failedAt' => 'datetime',
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
}
