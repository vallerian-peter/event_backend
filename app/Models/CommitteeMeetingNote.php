<?php

namespace App\Models;

use App\Enums\CommitteeNoteStatus;
use App\Enums\CommitteeNoteType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $meetingId
 * @property int|null $assignedMemberId
 * @property int|null $createdByUserId
 * @property string $content
 * @property CommitteeNoteType $type
 * @property CommitteeNoteStatus $status
 */
class CommitteeMeetingNote extends Model
{
    use HasFactory;

    protected $table = 'committee_meeting_notes';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'meetingId',
        'assignedMemberId',
        'createdByUserId',
        'content',
        'type',
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
            'type' => CommitteeNoteType::class,
            'status' => CommitteeNoteStatus::class,
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(CommitteeMeeting::class, 'meetingId');
    }

    public function assignedMember(): BelongsTo
    {
        return $this->belongsTo(EventCommitteeMember::class, 'assignedMemberId');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdByUserId');
    }
}
