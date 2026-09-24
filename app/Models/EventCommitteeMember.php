<?php

namespace App\Models;

use App\Enums\CommitteeMemberStatus;
use App\Enums\CommitteeRole;
use App\Enums\CommitteeTitle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $eventId
 * @property string $fullName
 * @property string|null $normalPhone
 * @property string|null $whatsappPhone
 * @property CommitteeTitle|null $title
 * @property CommitteeRole|null $role
 * @property string|null $customRole
 * @property CommitteeMemberStatus $status
 * @property string|null $welcomeSentAt
 * @property string|null $lastReminderSentAt
 * @property string|null $autoEventReminderLastSentAt
 * @property array $metadata
 */
class EventCommitteeMember extends Model
{
    use HasFactory;

    protected $table = 'event_committee_members';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'eventId',
        'fullName',
        'normalPhone',
        'whatsappPhone',
        'title',
        'role',
        'customRole',
        'status',
        'welcomeSentAt',
        'lastReminderSentAt',
        'autoEventReminderLastSentAt',
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
            'title' => CommitteeTitle::class,
            'role' => CommitteeRole::class,
            'status' => CommitteeMemberStatus::class,
            'metadata' => 'array',
            'welcomeSentAt' => 'datetime',
            'lastReminderSentAt' => 'datetime',
            'autoEventReminderLastSentAt' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }

    public function assignedNotes(): HasMany
    {
        return $this->hasMany(CommitteeMeetingNote::class, 'assignedMemberId');
    }
}
