<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_list_orders(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    public function test_seeded_user_can_sign_in(): void
    {
        $this->seed();

        $response = $this->postJson('/login', [
            'email' => 'alex.morgan@northline.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('email', 'alex.morgan@northline.test');

        $this->assertAuthenticated();
    }

    public function test_seed_builds_orders_across_every_status(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(30, Order::query()->count());

        $statuses = Order::query()->pluck('status')->unique()->sort()->values()->all();

        $this->assertSame(
            ['cancelled', 'completed', 'new', 'processing'],
            $statuses,
        );
    }

    public function test_orders_include_an_item_count(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'created_by' => $user->id,
        ]);
        OrderItem::factory()->count(2)->create([
            'order_id' => $order->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/orders');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.items_count', 2);
    }

    public function test_orders_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'new',
            'created_by' => $user->id,
        ]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/orders?status=new');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'new');
    }

    public function test_a_user_can_create_an_order_with_several_items(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_name' => 'V-belt A48', 'qty' => 2, 'unit_price' => 10],
                ['product_name' => 'Shaft seal 35x52x7', 'qty' => 1, 'unit_price' => 5],
            ],
        ]);

        $response->assertCreated();

        $order = Order::query()->first();

        $this->assertNotNull($order);
        $this->assertSame($user->id, $order->created_by);
        $this->assertSame('new', $order->status);
        $this->assertSame('25.00', $order->total_amount);
        $this->assertTrue($order->email_sent);
        $this->assertSame(2, $order->items()->count());
    }

    public function test_creating_an_order_requires_line_items(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [],
        ])->assertUnprocessable();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_user_can_delete_a_new_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'status' => 'new',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/orders/'.$order->id)
            ->assertOk();

        $this->assertModelMissing($order);
    }
}
