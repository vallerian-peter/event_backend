<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $userId
 * @property string $phone
 * @property string $otp
 * @property bool $isUsed
 * @property Carbon $expiresAt
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 * @property-read User $user
 */
class PasswordResetOtp extends Model
{
    use HasFactory;

    protected $table = 'password_reset_otps';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'userId',
        'phone',
        'otp',
        'isVerified',
        'verifiedAt',
        'isUsed',
        'expiresAt',
    ];

    protected function casts(): array
    {
        return [
            'isVerified' => 'boolean',
            'verifiedAt' => 'datetime',
            'isUsed' => 'boolean',
            'expiresAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /**
     * Check if OTP has exceeded its 5 minute window.
     */
    public function isExpired(): bool
    {
        return now()->greaterThan($this->expiresAt);
    }

    /**
     * Related user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
