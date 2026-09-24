<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $eventId
 * @property array|null $invitees
 * @property string|null $status
 * @property string|null $message
 */
class Message extends Model
{
    use HasFactory;

    protected $table = 'messages';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'eventId',
        'invitees',
        'status',
        'message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invitees' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }
}
