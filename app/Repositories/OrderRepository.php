<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepository
{
    /** @return LengthAwarePaginator<int, Order> */
    public function list(?OrderStatus $status = null, ?string $email = null, int $perPage = 15): LengthAwarePaginator;

    /** @param list<array{product_name: string, qty: int, unit_price: numeric-string}> $items */
    public function create(int $customerId, array $items, User $creator): Order;

    public function delete(Order $order): void;

    public function markEmailSent(Order $order): void;
}
