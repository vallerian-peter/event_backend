<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $templateId
 * @property int $eventId
 * @property string $paymentStatus
 */
class EventsTemplatePayment extends Model
{
    use HasFactory;

    protected $table = 'eventsTemplatePayment';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'templateId',
        'eventId',
        'paymentStatus',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'templateId');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }
}
