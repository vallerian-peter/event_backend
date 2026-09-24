<?php

namespace App\Models;

use App\Enums\PricingModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property int $version
 * @property string $name
 * @property string|null $description
 * @property float $priceUsd
 * @property PricingModel $pricingModel
 * @property float|null $pricePerInviteeTzs
 * @property array $capabilities
 * @property array $limits
 * @property bool $isActive
 */
class ServicePackage extends Model
{
    use HasFactory;

    protected $table = 'service_packages';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'slug',
        'version',
        'name',
        'description',
        'priceUsd',
        'pricingModel',
        'pricePerInviteeTzs',
        'capabilities',
        'limits',
        'isActive',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'priceUsd' => 'decimal:2',
            'pricePerInviteeTzs' => 'decimal:2',
            'pricingModel' => PricingModel::class,
            'capabilities' => 'array',
            'limits' => 'array',
            'isActive' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(EventPackageSubscription::class, 'servicePackageId');
    }
}
