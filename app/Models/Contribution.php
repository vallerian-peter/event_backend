<?php

namespace App\Models;

use App\Enums\ContributionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inviteeId
 * @property int|null $eventId
 * @property string $pledgeAmount
 * @property string $paidAmount
 * @property string|null $division
 * @property ContributionStatus $status
 * @property string|null $transactionId
 */
class Contribution extends Model
{
    use HasFactory;

    protected $table = 'contributions';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'inviteeId',
        'eventId',
        'pledgeAmount',
        'paidAmount',
        'division',
        'status',
        'transactionId',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContributionStatus::class,
        ];
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(Invitee::class, 'inviteeId');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }
}
