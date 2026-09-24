<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $userId
 * @property string|null $username
 * @property string|null $email
 * @property string $firstName
 * @property string|null $middleName
 * @property string $lastName
 * @property string|null $phone
 */
class SystemUser extends Model
{
    use HasFactory;

    protected $table = 'systemUsers';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'userId',
        'username',
        'email',
        'firstName',
        'middleName',
        'lastName',
        'phone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function eventPayments(): HasMany
    {
        return $this->hasMany(EventsPayment::class, 'systemUserId');
    }
}
