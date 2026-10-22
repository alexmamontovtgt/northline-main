<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

final class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ];
    }

    public function customerId(): int
    {
        return $this->integer('customer_id');
    }

    /** @return list<array{product_name: string, qty: int, unit_price: numeric-string}> */
    public function items(): array
    {
        /** @var list<array{product_name: string, qty: numeric-string|int, unit_price: numeric-string|int|float}> $items */
        $items = $this->validated('items');

        return array_map(fn (array $item) => [
            'product_name' => $item['product_name'],
            'qty' => (int) $item['qty'],
            'unit_price' => number_format((float) $item['unit_price'], 2, '.', ''),
        ], $items);
    }
}
