<?php

namespace App\Models;

use App\Enums\CommitteeMeetingStatus;
use App\Enums\CommitteeReminderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $eventId
 * @property string|null $title
 * @property string $meetingDate
 * @property string|null $meetingTime
 * @property string|null $location
 * @property int|null $intervalCount
 * @property string|null $intervalUnit
 * @property string|null $nextMeetingDate
 * @property CommitteeMeetingStatus $status
 * @property CommitteeReminderStatus $reminderStatus
 * @property string|null $lastReminderSentAt
 * @property array $metadata
 */
class CommitteeMeeting extends Model
{
    use HasFactory;

    protected $table = 'committee_meetings';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'eventId',
        'title',
        'meetingDate',
        'meetingTime',
        'location',
        'intervalCount',
        'intervalUnit',
        'nextMeetingDate',
        'status',
        'reminderStatus',
        'lastReminderSentAt',
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
            'status' => CommitteeMeetingStatus::class,
            'reminderStatus' => CommitteeReminderStatus::class,
            'intervalCount' => 'integer',
            'metadata' => 'array',
            'lastReminderSentAt' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CommitteeMeetingNote::class, 'meetingId');
    }
}
