<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $email
 * @property string $source
 * @property string $status
 * @property string|null $ipAddress
 * @property string|null $userAgent
 * @property string|null $verifiedAt
 * @property string $createdAt
 * @property string $updatedAt
 */
class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $table = 'newsletter_subscribers';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'email',
        'source',
        'status',
        'ipAddress',
        'userAgent',
        'verifiedAt',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verifiedAt' => 'datetime',
        ];
    }
}
