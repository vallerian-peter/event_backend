<?php

namespace App\Models;

use App\Enums\TemplateElementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $templateId
 * @property TemplateElementType $elementType
 * @property int $positionX
 * @property int $positionY
 * @property int|null $width
 * @property int|null $height
 * @property int|null $fontSize
 * @property string|null $fontColor
 * @property string|null $fontFamily
 * @property string $alignment
 * @property bool $isRequired
 * @property bool $isActive
 */
class TemplateElement extends Model
{
    use HasFactory;

    protected $table = 'template_elements';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'templateId',
        'elementType',
        'positionX',
        'positionY',
        'width',
        'height',
        'fontSize',
        'fontColor',
        'fontFamily',
        'alignment',
        'isRequired',
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
            'elementType' => TemplateElementType::class,
            'positionX' => 'integer',
            'positionY' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'fontSize' => 'integer',
            'isRequired' => 'boolean',
            'isActive' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'templateId');
    }
}
