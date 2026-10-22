<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $alex = User::factory()->create([
            'name' => 'Alex Morgan',
            'email' => 'alex.morgan@northline.test',
            'password' => 'password',
        ]);

        $sam = User::factory()->create([
            'name' => 'Sam Rivera',
            'email' => 'sam.rivera@northline.test',
            'password' => 'password',
        ]);

        $customers = collect([
            ['name' => 'Harbor Bearing Co.', 'email' => 'orders@harbor-bearing.test'],
            ['name' => 'Redwood Logistics', 'email' => 'purchasing@redwood-logistics.test'],
            ['name' => 'Fieldstone Fasteners', 'email' => 'desk@fieldstone-fasteners.test'],
            ['name' => 'Pike Marine Supply', 'email' => 'buying@pike-marine.test'],
            ['name' => 'Linden Precision', 'email' => 'ops@linden-precision.test'],
            ['name' => 'Copperline Cable', 'email' => 'supply@copperline-cable.test'],
            ['name' => 'Westbridge Hydraulics', 'email' => 'parts@westbridge-hydraulics.test'],
            ['name' => 'Maple Tool Works', 'email' => 'warehouse@maple-tool.test'],
        ])->map(fn (array $customer) => Customer::query()->create($customer));

        $catalog = [
            ['name' => 'Roller bearing 6204-2RS', 'price' => 14.20],
            ['name' => 'Hydraulic hose 3/8 in, 2 m', 'price' => 27.80],
            ['name' => 'M8 flange bolt, box of 100', 'price' => 16.40],
            ['name' => 'Shaft seal 35x52x7', 'price' => 6.75],
            ['name' => 'Control cable 4 m', 'price' => 31.00],
            ['name' => 'Lithium grease cartridge', 'price' => 8.90],
            ['name' => 'V-belt A48', 'price' => 12.60],
            ['name' => 'Pressure gauge 0-160 psi', 'price' => 22.15],
        ];

        $statuses = [
            'new', 'processing', 'completed', 'new', 'cancelled',
            'completed', 'processing', 'new', 'completed', 'new',
            'processing', 'completed', 'cancelled', 'new', 'completed',
            'processing', 'completed', 'new', 'cancelled', 'completed',
            'new', 'processing', 'completed', 'new', 'completed',
            'cancelled', 'processing', 'completed', 'new', 'completed',
            'processing', 'new', 'cancelled', 'completed', 'new',
            'completed',
        ];

        foreach ($statuses as $index => $status) {
            $customer = $customers->get($index % $customers->count()) ?? throw new \RuntimeException('Missing seeded customer.');
            $owner = $index % 4 === 0 ? $sam : $alex;
            $createdAt = now()->subDays(36 - $index)->setTime(8 + ($index % 8), ($index * 11) % 60);
            $lineCount = ($index % 3) + 1;
            $total = 0;
            $lines = [];

            for ($line = 0; $line < $lineCount; $line++) {
                $product = $catalog[($index + $line) % count($catalog)];
                $qty = $line + 1;
                $lineTotal = round($qty * $product['price'], 2);
                $total += $lineTotal;
                $lines[] = [
                    'product_name' => $product['name'],
                    'qty' => $qty,
                    'unit_price' => $product['price'],
                ];
            }

            $order = Order::query()->create([
                'customer_id' => $customer->id,
                'status' => $status,
                'total_amount' => round($total, 2),
                'email_sent' => in_array($status, ['processing', 'completed'], true),
                'created_by' => $owner->id,
            ]);

            $order->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            $order->items()->createMany($lines);
        }
    }
}
