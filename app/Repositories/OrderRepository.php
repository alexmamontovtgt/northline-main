<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    /** @return LengthAwarePaginator<int, Order> */
    public function list(?OrderStatus $status = null, ?string $email = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::query()->with(['customer', 'items'])->latest()->latest('id');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($email !== null) {
            $query->whereRelation('customer', 'email', $email);
        }

        return $query->paginate($perPage);
    }

    /** @param list<array{product_name: string, qty: int, unit_price: numeric-string}> $items */
    public function create(int $customerId, array $items, User $creator): Order
    {
        $total = array_reduce(
            $items,
            fn (string $sum, array $item) => bcadd($sum, bcmul((string) $item['qty'], $item['unit_price'], 2), 2),
            '0.00',
        );

        $order = DB::transaction(function () use ($customerId, $items, $creator, $total) {
            $order = Order::query()->create([
                'customer_id' => $customerId,
                'status' => OrderStatus::New,
                'total_amount' => $total,
                'email_sent' => false,
                'created_by' => $creator->id,
            ]);

            $order->items()->createMany($items);

            return $order;
        });

        return $order->load(['customer', 'items']);
    }

    public function markEmailSent(Order $order): void
    {
        $order->forceFill(['email_sent' => true])->save();
    }
}
