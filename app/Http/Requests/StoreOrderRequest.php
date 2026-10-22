<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'customer_id' => ['bail', 'required', 'integer', 'exists:customers,id'],
            'items' => ['bail', 'required', 'array', 'min:1'],
            'items.*.product_name' => ['bail', 'required', 'string', 'max:255'],
            'items.*.qty' => ['bail', 'required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['bail', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.Order::MAX_TOTAL],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $price = 'Line :position: unit price must be between 0 and '.number_format((float) Order::MAX_TOTAL, 2).'.';

        return [
            'customer_id.required' => 'Select a customer.',
            'customer_id.integer' => 'The customer is invalid.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'items.required' => 'Add at least one order line.',
            'items.array' => 'The order lines are invalid.',
            'items.min' => 'Add at least one order line.',
            'items.*.product_name.required' => 'Line :position: enter a product name.',
            'items.*.product_name.string' => 'Line :position: the product name is invalid.',
            'items.*.product_name.max' => 'Line :position: the product name may not be longer than :max characters.',
            'items.*.qty.required' => 'Line :position: enter a quantity.',
            'items.*.qty.integer' => 'Line :position: the quantity must be a whole number.',
            'items.*.qty.min' => 'Line :position: the quantity must be at least :min.',
            'items.*.qty.max' => 'Line :position: the quantity may not be greater than :max.',
            'items.*.unit_price.required' => 'Line :position: enter a unit price.',
            'items.*.unit_price.numeric' => 'Line :position: the unit price must be a number.',
            'items.*.unit_price.decimal' => 'Line :position: the unit price may have at most 2 decimal places.',
            'items.*.unit_price.min' => $price,
            'items.*.unit_price.max' => $price,
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $items = $this->input('items');

            if ($validator->errors()->isNotEmpty() || ! is_array($items)) {
                return;
            }

            /** @var list<array{qty: int|numeric-string, unit_price: int|float|numeric-string}> $items */
            $items = array_values($items);

            if (bccomp(Order::totalFor($items), Order::MAX_TOTAL, 2) === 1) {
                $validator->errors()->add('items', 'The order total may not exceed '.number_format((float) Order::MAX_TOTAL, 2).'.');
            }
        }];
    }

    public function customerId(): int
    {
        return $this->integer('customer_id');
    }

    /** @return list<array{product_name: string, qty: int, unit_price: numeric-string}> */
    public function items(): array
    {
        /** @var array<array{product_name: string, qty: numeric-string|int, unit_price: numeric-string|int|float}> $validated */
        $validated = $this->validated('items');
        $items = array_values($validated);

        return array_map(fn (array $item) => [
            'product_name' => $item['product_name'],
            'qty' => (int) $item['qty'],
            'unit_price' => number_format((float) $item['unit_price'], 2, '.', ''),
        ], $items);
    }
}
