<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $customer = $this->customer ?? throw new \RuntimeException('Order is missing its customer.');

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'total_amount' => $this->total_amount,
            'email_sent' => $this->email_sent,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'items_count' => (int) $this->items_count,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
            ],
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }
}
