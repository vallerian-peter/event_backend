<?php

namespace App\Models;

use App\Enums\PurchaseType;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $eventId
 * @property int $servicePackageId
 * @property int|null $paymentId
 * @property PurchaseType $purchaseType
 * @property SubscriptionStatus $status
 * @property array $packageSnapshot
 * @property float $recordedAmountTzs
 * @property int $inviteeLimit
 * @property string $activatedAt
 * @property string|null $expiresAt
 */
class EventPackageSubscription extends Model
{
    use HasFactory;

    protected $table = 'event_package_subscriptions';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'eventId',
        'servicePackageId',
        'paymentId',
        'purchaseType',
        'status',
        'packageSnapshot',
        'recordedAmountTzs',
        'inviteeLimit',
        'activatedAt',
        'expiresAt',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchaseType' => PurchaseType::class,
            'status' => SubscriptionStatus::class,
            'packageSnapshot' => 'array',
            'recordedAmountTzs' => 'decimal:2',
            'inviteeLimit' => 'integer',
            'activatedAt' => 'datetime',
            'expiresAt' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'servicePackageId');
    }

    public function featureBalances(): HasMany
    {
        return $this->hasMany(EventFeatureBalance::class, 'subscriptionId');
    }
}
