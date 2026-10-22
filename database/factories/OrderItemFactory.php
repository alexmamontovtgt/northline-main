<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_name' => fake()->randomElement([
                'Roller bearing 6204-2RS',
                'Hydraulic hose 3/8 in, 2 m',
                'M8 flange bolt, box of 100',
                'Shaft seal 35x52x7',
            ]),
            'qty' => fake()->numberBetween(1, 4),
            'unit_price' => fake()->randomFloat(2, 5, 40),
        ];
    }
}
