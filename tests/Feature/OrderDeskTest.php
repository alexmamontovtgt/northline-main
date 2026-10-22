<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Listeners\SendOrderPlacedEmail;
use App\Listeners\SyncOrderToCrm;
use App\Mail\OrderPlacedMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderDeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_list_orders(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    public function test_guests_cannot_create_orders(): void
    {
        $this->postJson('/api/orders', [])->assertUnauthorized();
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

        $this->actingAs($user)->getJson('/api/orders?email=alic@example.test')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)->getJson('/api/orders?email=alice')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

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

    public function test_order_total_is_calculated_without_float_drift(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_name' => 'Part A', 'qty' => 3, 'unit_price' => 0.1],
                ['product_name' => 'Part B', 'qty' => 7, 'unit_price' => '19.99'],
                ['product_name' => 'Part C', 'qty' => 1, 'unit_price' => 1.15],
            ],
        ])->assertCreated();

        $this->assertSame('141.38', Order::query()->firstOrFail()->total_amount);
        $this->assertSame('0.10', OrderItem::query()->orderBy('id')->firstOrFail()->unit_price);
    }

    public function test_item_quantity_is_limited(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        foreach ([0, 10001] as $qty) {
            $this->actingAs($user)->postJson('/api/orders', [
                'customer_id' => $customer->id,
                'items' => [['product_name' => 'Part A', 'qty' => $qty, 'unit_price' => '1.00']],
            ])->assertUnprocessable()->assertJsonValidationErrors('items.0.qty');
        }

        $this->actingAs($user)->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [['product_name' => 'Part A', 'qty' => 10000, 'unit_price' => '1.00']],
        ])->assertCreated();
    }

    public function test_deleting_a_missing_order_returns_a_friendly_message(): void
    {
        $this->actingAs(User::factory()->create())
            ->deleteJson('/api/orders/9999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'The requested record was not found.']);
    }

    public function test_order_validation_errors_are_readable(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)->postJson('/api/orders', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.customer_id.0', 'Select a customer.')
            ->assertJsonPath('errors.items.0', 'Add at least one order line.');

        $this->actingAs($user)->postJson('/api/orders', ['customer_id' => 999, 'items' => [['product_name' => 'A', 'qty' => 1, 'unit_price' => '1.00']]])
            ->assertJsonPath('errors.customer_id.0', 'The selected customer does not exist.');

        $this->actingAs($user)->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_name' => 'A', 'qty' => 1, 'unit_price' => '1.00'],
                ['product_name' => '', 'qty' => 'abc', 'unit_price' => 'abc'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.1.product_name' => 'Line 2: enter a product name.',
                'items.1.qty' => 'Line 2: the quantity must be a whole number.',
                'items.1.unit_price' => 'Line 2: the unit price must be a number.',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_total_cannot_exceed_the_column_limit(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_name' => 'A', 'qty' => 10000, 'unit_price' => '99999999.99'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.items.0', 'The order total may not exceed 99,999,999.99.');

        $this->actingAs($user)->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [['product_name' => 'A', 'qty' => 1, 'unit_price' => '99999999.99']],
        ])->assertCreated();

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_items_sent_as_an_object_are_stored_as_lines(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => ['a' => ['product_name' => 'A', 'qty' => 2, 'unit_price' => '1.50']],
        ])->assertCreated();

        $this->assertSame('3.00', Order::query()->firstOrFail()->total_amount);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_list_validation_errors_are_readable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/orders?status=bogus')
            ->assertJsonPath('errors.status.0', 'The status must be one of: new, processing, completed, cancelled.');

        $this->actingAs($user)->getJson('/api/orders?email=alice')
            ->assertJsonPath('errors.email.0', 'Enter a full email address, for example buyer@company.test.');

        $this->actingAs($user)->getJson('/api/orders?page=0')
            ->assertUnprocessable()
            ->assertJsonPath('errors.page.0', 'The page must be at least 1.');

        $this->actingAs($user)->getJson('/api/orders?per_page=1000')
            ->assertJsonPath('errors.per_page.0', 'The page size may not be greater than 100.');

        $this->actingAs($user)->getJson('/api/orders?page=99')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unit_price_cannot_have_more_than_two_decimals(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [['product_name' => 'Part A', 'qty' => 1, 'unit_price' => '1.999']],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.unit_price');
    }

    public function test_creating_an_order_dispatches_order_placed(): void
    {
        Event::fake([OrderPlaced::class]);

        $user = User::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [['product_name' => 'V-belt A48', 'qty' => 1, 'unit_price' => 10]],
        ])->assertCreated();

        Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->order->customer_id === $customer->id);
        $this->assertFalse(Order::query()->firstOrFail()->email_sent);
    }

    public function test_order_placed_email_is_sent_once_and_marks_the_order(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['email_sent' => false]);

        $listener = app(SendOrderPlacedEmail::class);
        $listener->handle(new OrderPlaced($order));
        $listener->handle(new OrderPlaced($order->fresh()));

        Mail::assertSent(OrderPlacedMail::class, 1);
        $this->assertTrue($order->fresh()->email_sent);
    }

    public function test_customer_emails_are_stored_in_lower_case(): void
    {
        $customer = Customer::factory()->create(['email' => '  Buyer@Example.TEST ']);

        $this->assertSame('buyer@example.test', $customer->fresh()->email);
    }

    public function test_queued_listeners_are_discarded_when_the_order_was_deleted(): void
    {
        config(['queue.default' => 'database']);
        Mail::fake();

        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::New, 'created_by' => $user->id]);

        OrderPlaced::dispatch($order);
        $this->assertDatabaseCount('jobs', 2);

        $order->delete();

        $this->artisan('queue:work', ['--stop-when-empty' => true, '--tries' => 1])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        Mail::assertNothingSent();
    }

    public function test_crm_sync_failure_is_logged(): void
    {
        Log::spy();

        $customer = Customer::factory()->create(['email' => 'parts@westbridge-hydraulics.test']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $listener = app(SyncOrderToCrm::class);

        try {
            $listener->handle(new OrderPlaced($order));
            $this->fail('Expected the CRM to reject the order.');
        } catch (\Throwable $exception) {
            $listener->failed(new OrderPlaced($order), $exception);
        }

        Log::shouldHaveReceived('warning')->once()->with('CRM sync failed', \Mockery::on(fn ($context) => $context['order_id'] === $order->id));
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
            ->assertForbidden()
            ->assertJsonPath('message', 'You can only delete orders you created.');

        $this->assertModelExists($order);
    }

    public function test_only_new_orders_can_be_deleted(): void
    {
        $user = User::factory()->create();

        foreach (['processing', 'completed', 'cancelled'] as $status) {
            $order = Order::factory()->create(['status' => $status, 'created_by' => $user->id]);

            $this->actingAs($user)
                ->deleteJson('/api/orders/'.$order->id)
                ->assertForbidden()
                ->assertJsonPath('message', 'Only orders with status "new" can be deleted.');

            $this->assertModelExists($order);
        }
    }
}
