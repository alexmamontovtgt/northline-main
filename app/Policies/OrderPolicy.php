<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class OrderPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Order $order): Response
    {
        return match (true) {
            $order->created_by !== $user->id => Response::deny('You can only delete orders you created.'),
            $order->status !== OrderStatus::New => Response::deny('Only orders with status "new" can be deleted.'),
            default => Response::allow(),
        };
    }
}
