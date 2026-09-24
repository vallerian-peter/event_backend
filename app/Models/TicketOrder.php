<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $orderNumber
 * @property int $eventId
 * @property int|null $userId
 * @property string $customerName
 * @property string $customerEmail
 * @property string $customerPhone
 * @property float $totalAmount
 * @property float $paidAmount
 * @property string $currency
 * @property string $paymentMethod
 * @property string|null $transactionId
 * @property OrderStatus $status
 */
class TicketOrder extends Model
{
    use HasFactory;

    protected $table = 'ticket_orders';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'orderNumber',
        'eventId',
        'userId',
        'customerName',
        'customerEmail',
        'customerPhone',
        'totalAmount',
        'paidAmount',
        'currency',
        'paymentMethod',
        'transactionId',
        'status',
    ];

    /**
     * Boot model to generate orderNumber.
     */
    protected static function booted(): void
    {
        static::creating(function (TicketOrder $order): void {
            if (empty($order->orderNumber)) {
                $order->orderNumber = 'ORD-'.date('Ymd').'-'.Str::upper(Str::random(6));
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'totalAmount' => 'decimal:2',
            'paidAmount' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'orderId');
    }
}
