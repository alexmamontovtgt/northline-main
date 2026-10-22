<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
            $email = mb_strtolower($email);

            $query->whereHas('customer', fn ($customer) => $customer->whereRaw('lower(email) = ?', [$email]));
        }

        return $query->paginate($perPage);
    }
}
