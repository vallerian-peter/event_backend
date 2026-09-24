<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $referenceCode
 * @property string $fullName
 * @property string $email
 * @property string $phone
 * @property string $eventType
 * @property string|null $eventDate
 * @property string|null $eventTime
 * @property string|null $venueLocation
 * @property string|null $estimatedGuests
 * @property string|null $budgetRange
 * @property array|null $requestedServices
 * @property string $message
 * @property string $status
 * @property string|null $adminNotes
 * @property int|null $assignedToUserId
 * @property string|null $contactedAt
 * @property string $createdAt
 * @property string $updatedAt
 */
class ProjectBrief extends Model
{
    use HasFactory;

    protected $table = 'project_briefs';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'referenceCode',
        'fullName',
        'email',
        'phone',
        'eventType',
        'eventDate',
        'eventTime',
        'venueLocation',
        'estimatedGuests',
        'budgetRange',
        'requestedServices',
        'message',
        'status',
        'adminNotes',
        'assignedToUserId',
        'contactedAt',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requestedServices' => 'array',
            'eventDate' => 'date',
            'contactedAt' => 'datetime',
        ];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignedToUserId');
    }
}
