<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    public function creator(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function customerId(): int
    {
        return $this->integer('customer_id');
    }

    /** @return list<array{product_name: string, qty: int, unit_price: float}> */
    public function items(): array
    {
        /** @var list<array{product_name: string, qty: numeric-string|int, unit_price: numeric-string|int|float}> $items */
        $items = $this->validated('items');

        return array_map(fn (array $item) => [
            'product_name' => $item['product_name'],
            'qty' => (int) $item['qty'],
            'unit_price' => (float) $item['unit_price'],
        ], $items);
    }
}
