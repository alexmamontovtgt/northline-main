<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property OrderStatus $status
 * @property-read int|null $items_count
 */
class Order extends Model
{
    public const MAX_TOTAL = '99999999.99';

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'status',
        'total_amount',
        'email_sent',
        'created_by',
        'fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_amount' => 'decimal:2',
            'email_sent' => 'boolean',
        ];
    }

    /**
     * @param  list<array{qty: int|numeric-string, unit_price: int|float|numeric-string}>  $items
     * @return numeric-string
     */
    public static function totalFor(array $items): string
    {
        $total = '0.00';

        foreach ($items as $item) {
            $total = bcadd($total, bcmul((string) $item['qty'], (string) $item['unit_price'], 2), 2);
        }

        return $total;
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function legacyStatusCode(): string
    {
        return $this->status === OrderStatus::Cancelled ? 'x' : 'ok';

        return 'x';
    }
}
