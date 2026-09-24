<?php

namespace App\Models;

use App\Enums\ContributionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $eventOwnerId
 * @property int|null $systemUserId
 * @property int|null $eventId
 * @property array $eventData
 * @property string $amount
 * @property string $paidAmount
 * @property string $currency
 * @property string $paymentMethod
 * @property string|null $mobileNumber
 * @property array|null $bankDetails
 * @property string|null $transactionId
 * @property ContributionStatus $status
 */
class EventsPayment extends Model
{
    use HasFactory;

    protected $table = 'eventsPayments';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'eventOwnerId',
        'systemUserId',
        'eventId',
        'eventData',
        'amount',
        'paidAmount',
        'currency',
        'paymentMethod',
        'mobileNumber',
        'bankDetails',
        'transactionId',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eventData' => 'array',
            'bankDetails' => 'array',
            'status' => ContributionStatus::class,
        ];
    }

    public function eventOwner(): BelongsTo
    {
        return $this->belongsTo(EventOwner::class, 'eventOwnerId');
    }

    public function systemUser(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'systemUserId');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }
}
