<?php

namespace App\Models;

use App\Enums\EventCategory;
use App\Enums\EventDivision;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\TemplateTier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property EventCategory $eventCategory
 * @property bool $isPublic
 * @property bool $isFeatured
 * @property string|null $category
 * @property EventStatus $status
 * @property string|null $venueName
 * @property string|null $address
 * @property string|null $startDate
 * @property string|null $endDate
 * @property string $eventTime
 * @property string|null $contact1
 * @property string|null $contact2
 * @property string|null $description
 * @property string|null $coverImageUrl
 * @property string|null $bannerUrl
 * @property string|null $flyerUrl
 * @property EventType|null $typeOfEvent
 * @property int|null $templateId
 * @property int|null $eventOwnerId
 * @property bool $autoSendSms
 * @property string $smsTimeDifference
 * @property EventDivision|null $divisions
 * @property array|null $divisionsPrices
 * @property bool $contributionsNeeded
 * @property int $inviteeLimit
 * @property string $servicePackageSlug
 * @property array $serviceFeatures
 * @property TemplateTier|null $invitationTemplateTier
 */
class Event extends Model
{
    use HasFactory;

    protected $table = 'events';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'title',
        'slug',
        'eventCategory',
        'isPublic',
        'isFeatured',
        'category',
        'status',
        'venueName',
        'address',
        'startDate',
        'endDate',
        'eventTime',
        'contact1',
        'contact2',
        'description',
        'coverImageUrl',
        'bannerUrl',
        'flyerUrl',
        'typeOfEvent',
        'templateId',
        'eventOwnerId',
        'autoSendSms',
        'smsTimeDifference',
        'divisions',
        'divisionsPrices',
        'contributionsNeeded',
        'inviteeLimit',
        'servicePackageSlug',
        'serviceFeatures',
        'invitationTemplateTier',
    ];

    /**
     * Boot the model to auto-generate slug.
     */
    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->title).'-'.Str::lower(Str::random(6));
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
            'eventCategory' => EventCategory::class,
            'status' => EventStatus::class,
            'typeOfEvent' => EventType::class,
            'divisions' => EventDivision::class,
            'invitationTemplateTier' => TemplateTier::class,
            'isPublic' => 'boolean',
            'isFeatured' => 'boolean',
            'autoSendSms' => 'boolean',
            'contributionsNeeded' => 'boolean',
            'inviteeLimit' => 'integer',
            'divisionsPrices' => 'array',
            'serviceFeatures' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'templateId');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(EventOwner::class, 'eventOwnerId');
    }

    public function invitees(): HasMany
    {
        return $this->hasMany(Invitee::class, 'eventId');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'eventId');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class, 'eventId');
    }

    public function ticketOrders(): HasMany
    {
        return $this->hasMany(TicketOrder::class, 'eventId');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'eventId');
    }

    public function entryActivities(): HasMany
    {
        return $this->hasMany(EntryActivity::class, 'eventId');
    }

    public function packageSubscriptions(): HasMany
    {
        return $this->hasMany(EventPackageSubscription::class, 'eventId');
    }

    public function featureBalances(): HasMany
    {
        return $this->hasMany(EventFeatureBalance::class, 'eventId');
    }

    public function messageRecords(): HasMany
    {
        return $this->hasMany(MessageRecord::class, 'eventId');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'eventId');
    }

    public function committeeMembers(): HasMany
    {
        return $this->hasMany(EventCommitteeMember::class, 'eventId');
    }

    public function committeeMeetings(): HasMany
    {
        return $this->hasMany(CommitteeMeeting::class, 'eventId');
    }

    public function isNormal(): bool
    {
        return $this->eventCategory === EventCategory::Normal;
    }

    public function isTicketed(): bool
    {
        return $this->eventCategory === EventCategory::Ticketed;
    }
}
