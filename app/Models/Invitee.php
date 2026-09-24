<?php

namespace App\Models;

use App\Enums\ContributionStatus;
use App\Enums\EventDivision;
use App\Enums\InviteeStatus;
use App\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $eventId
 * @property string $fullName
 * @property string $title
 * @property string|null $email
 * @property string|null $normalPhone
 * @property string|null $whatsappPhone
 * @property InviteeStatus $status
 * @property SmsStatus $smsStatus
 * @property EventDivision|null $division
 * @property ContributionStatus $contributionStatus
 * @property string|null $entryPassCode
 * @property string|null $entryPassTokenHash
 * @property string|null $entryPassConsumedAt
 * @property string|null $entryPassScannedByUuid
 * @property string|null $autoReminderLastSentAt
 * @property string|null $autoEntryLastSentAt
 * @property string|null $autoParticipationThanksSentAt
 */
class Invitee extends Model
{
    use HasFactory;

    protected $table = 'invitees';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $attributes = [
        'title' => 'Normal Person',
        'status' => InviteeStatus::Active,
        'smsStatus' => SmsStatus::Pending,
        'contributionStatus' => ContributionStatus::Pending,
    ];

    protected $fillable = [
        'eventId',
        'fullName',
        'title',
        'email',
        'normalPhone',
        'whatsappPhone',
        'status',
        'smsStatus',
        'division',
        'contributionStatus',
        'entryPassCode',
        'entryPassTokenHash',
        'entryPassConsumedAt',
        'entryPassScannedByUuid',
        'autoReminderLastSentAt',
        'autoEntryLastSentAt',
        'autoParticipationThanksSentAt',
    ];

    /**
     * Boot the model to auto-generate short entry pass code and token hash.
     */
    protected static function booted(): void
    {
        static::creating(function (Invitee $invitee): void {
            if (empty($invitee->entryPassCode)) {
                $invitee->entryPassCode = Str::upper(Str::random(9));
            }
            if (empty($invitee->entryPassTokenHash)) {
                $invitee->entryPassTokenHash = hash('sha256', $invitee->entryPassCode);
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
            'status' => InviteeStatus::class,
            'smsStatus' => SmsStatus::class,
            'division' => EventDivision::class,
            'contributionStatus' => ContributionStatus::class,
            'entryPassConsumedAt' => 'datetime',
            'autoReminderLastSentAt' => 'datetime',
            'autoEntryLastSentAt' => 'datetime',
            'autoParticipationThanksSentAt' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'inviteeId');
    }

    public function entryActivities(): HasMany
    {
        return $this->hasMany(EntryActivity::class, 'inviteeId');
    }

    public function messageRecords(): HasMany
    {
        return $this->hasMany(MessageRecord::class, 'inviteeId');
    }

    public function markAsConsumed(?string $scannedByUuid = null): void
    {
        $this->update([
            'entryPassConsumedAt' => now(),
            'entryPassScannedByUuid' => $scannedByUuid,
            'status' => InviteeStatus::Attended,
        ]);
    }
}
