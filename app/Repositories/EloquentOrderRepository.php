<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentOrderRepository implements OrderRepository
{
    private const DUPLICATE_WINDOW_SECONDS = 10;

    /** @return LengthAwarePaginator<int, Order> */
    public function list(?OrderStatus $status = null, ?string $email = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::query()->with('customer')->withCount('items')->latest()->latest('id');

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
        $order = DB::transaction(function () use ($customerId, $items, $creator) {
            $order = Order::query()->createOrFirst(
                ['fingerprint' => $this->fingerprint($creator, $customerId, $items)],
                [
                    'customer_id' => $customerId,
                    'status' => OrderStatus::New,
                    'total_amount' => Order::totalFor($items),
                    'email_sent' => false,
                    'created_by' => $creator->id,
                ],
            );

            if ($order->wasRecentlyCreated) {
                $order->items()->createMany($items);
            }

            return $order;
        });

        return $order->load('customer')->loadCount('items');
    }

    /** @param list<array{product_name: string, qty: int, unit_price: numeric-string}> $items */
    private function fingerprint(User $creator, int $customerId, array $items): string
    {
        $lines = array_map(fn (array $item) => json_encode([$item['product_name'], $item['qty'], $item['unit_price']]), $items);

        sort($lines);

        $window = intdiv(now()->getTimestamp(), self::DUPLICATE_WINDOW_SECONDS);

        return hash('sha256', json_encode([$creator->id, $customerId, $window, $lines], JSON_THROW_ON_ERROR));
    }

    public function delete(Order $order): void
    {
        $order->delete();
    }

    public function markEmailSent(Order $order): void
    {
        $order->forceFill(['email_sent' => true])->save();
    }
}
