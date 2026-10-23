<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $email
 * @property string|null $phone
 * @property string $otp
 * @property \Illuminate\Support\Carbon $startTime
 * @property \Illuminate\Support\Carbon|null $endTime
 * @property bool $isVerified
 * @property \Illuminate\Support\Carbon $createdAt
 * @property \Illuminate\Support\Carbon $updatedAt
 */
class EmailOtpVerify extends Model
{
    use HasFactory;

    protected $table = 'emailOtpVerify';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'email',
        'phone',
        'otp',
        'startTime',
        'endTime',
        'isVerified',
    ];

    protected function casts(): array
    {
        return [
            'startTime' => 'datetime',
            'endTime' => 'datetime',
            'isVerified' => 'boolean',
        ];
    }

    public function isExpired(): bool
    {
        return $this->endTime ? now()->isAfter($this->endTime) : true;
    }
}
