<?php

namespace App\Models;

use App\Enums\TemplateRenderMode;
use App\Enums\TemplateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $pathUrl
 * @property string|null $previewImageUrl
 * @property float $price
 * @property bool $isPremium
 * @property string|null $category
 * @property TemplateType $templateType
 * @property int $canvasWidth
 * @property int $canvasHeight
 * @property TemplateRenderMode $renderMode
 * @property bool $isActive
 */
class Template extends Model
{
    use HasFactory;

    protected $table = 'templates';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'pathUrl',
        'previewImageUrl',
        'price',
        'isPremium',
        'category',
        'templateType',
        'canvasWidth',
        'canvasHeight',
        'renderMode',
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
            'price' => 'decimal:2',
            'isPremium' => 'boolean',
            'isActive' => 'boolean',
            'canvasWidth' => 'integer',
            'canvasHeight' => 'integer',
            'templateType' => TemplateType::class,
            'renderMode' => TemplateRenderMode::class,
        ];
    }

    public function elements(): HasMany
    {
        return $this->hasMany(TemplateElement::class, 'templateId');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'templateId');
    }

    public function templatePayments(): HasMany
    {
        return $this->hasMany(EventsTemplatePayment::class, 'templateId');
    }

    /**
     * Helper to get the QR code coordinate element.
     */
    public function getQrElement(): ?TemplateElement
    {
        return $this->elements()->where('elementType', 'qr_code')->first();
    }

    /**
     * Helper to get the invitee name coordinate element.
     */
    public function getNameElement(): ?TemplateElement
    {
        return $this->elements()->where('elementType', 'invitee_name')->first();
    }

    public function supportsQrOnly(): bool
    {
        return $this->renderMode === TemplateRenderMode::QrOnly;
    }

    public function supportsQrAndName(): bool
    {
        return $this->renderMode === TemplateRenderMode::QrAndName;
    }
}
