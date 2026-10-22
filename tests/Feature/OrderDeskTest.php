<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
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

        $statuses = Order::query()->pluck('status')->map(fn (OrderStatus $status) => $status->value)->unique()->sort()->values()->all();

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

    public function test_orders_can_be_filtered_by_every_status(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        foreach (OrderStatus::cases() as $status) {
            Order::factory()->create(['customer_id' => $customer->id, 'status' => $status, 'created_by' => $user->id]);
        }

        foreach (OrderStatus::cases() as $status) {
            $this->actingAs($user)->getJson('/api/orders?status='.$status->value)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.status', $status->value);
        }

        $this->actingAs($user)->getJson('/api/orders?status=bogus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_orders_can_be_filtered_by_customer_email(): void
    {
        $user = User::factory()->create();
        $alice = Customer::factory()->create(['email' => 'alice@example.test']);
        $bob = Customer::factory()->create(['email' => 'bob@example.test']);

        Order::factory()->create(['customer_id' => $alice->id, 'status' => 'new', 'created_by' => $user->id]);
        Order::factory()->create(['customer_id' => $alice->id, 'status' => 'completed', 'created_by' => $user->id]);
        Order::factory()->create(['customer_id' => $bob->id, 'status' => 'new', 'created_by' => $user->id]);

        $this->actingAs($user)->getJson('/api/orders?email=ALICE@example.test')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.customer.email', 'alice@example.test');

        $this->actingAs($user)->getJson('/api/orders?email=alice')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)->getJson('/api/orders?email=alice@example.test&status=new')
            ->assertOk()
            ->assertJsonCount(1, 'data');
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
        $this->assertSame(OrderStatus::New, $order->status);
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

    public function test_orders_expose_whether_they_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Order::factory()->create(['status' => 'new', 'created_by' => $user->id]);
        $processing = Order::factory()->create(['status' => 'processing', 'created_by' => $user->id]);
        $theirs = Order::factory()->create(['status' => 'new', 'created_by' => $other->id]);

        $flags = collect($this->actingAs($user)->getJson('/api/orders')->assertOk()->json('data'))
            ->pluck('can_delete', 'id');

        $this->assertTrue($flags[$mine->id]);
        $this->assertFalse($flags[$processing->id]);
        $this->assertFalse($flags[$theirs->id]);
    }

    public function test_orders_are_paginated(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(5)->create(['created_by' => $user->id]);

        $this->actingAs($user)->getJson('/api/orders?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 5);

        $this->actingAs($user)->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15);

        $this->actingAs($user)->getJson('/api/orders?per_page=500')
            ->assertUnprocessable();
    }

    public function test_a_user_cannot_delete_another_users_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['status' => 'new', 'created_by' => $owner->id]);

        $this->actingAs($other)
            ->deleteJson('/api/orders/'.$order->id)
            ->assertForbidden();

        $this->assertModelExists($order);
    }

    public function test_only_new_orders_can_be_deleted(): void
    {
        $user = User::factory()->create();

        foreach (['processing', 'completed', 'cancelled'] as $status) {
            $order = Order::factory()->create(['status' => $status, 'created_by' => $user->id]);

            $this->actingAs($user)
                ->deleteJson('/api/orders/'.$order->id)
                ->assertForbidden();

            $this->assertModelExists($order);
        }
    }
}
