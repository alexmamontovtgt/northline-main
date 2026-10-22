<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Services\CrmSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()->latest();

        if ($request->filled('status')) {
            $orders->where('status', (string) $request->string('status'));
        }

        $orders = $orders->get();

        return response()->json([
            'data' => $orders->map(function (Order $order) {
                $customer = $order->customer ?? throw new \RuntimeException('Order is missing its customer.');

                return [
                    'id' => $order->id,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'email_sent' => $order->email_sent,
                    'created_at' => $order->created_at?->toIso8601String(),
                    'created_by' => $order->created_by,
                    'items_count' => $order->items->count(),
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                    ],
                ];
            })->values(),
        ]);
    }

    public function store(Request $request, CrmSyncService $crm): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $total = 0;

        foreach ($validated['items'] as $item) {
            $total += round(((int) $item['qty']) * ((float) $item['unit_price']), 2);
        }

        $actor = $request->user() ?? throw new \RuntimeException('Authenticated user is missing.');

        $order = DB::transaction(function () use ($validated, $actor, $total) {
            $order = Order::query()->create([
                'customer_id' => $validated['customer_id'],
                'status' => 'new',
                'total_amount' => round($total, 2),
                'email_sent' => false,
                'created_by' => $actor->id,
            ]);

            $order->items()->createMany(array_map(fn (array $item) => [
                'product_name' => $item['product_name'],
                'qty' => $item['qty'],
                'unit_price' => $item['unit_price'],
            ], $validated['items']));

            return $order;
        });

        $order->load(['customer', 'items']);

        $customer = $order->customer ?? throw new \RuntimeException('Order is missing its customer.');

        Mail::to($customer->email)->send(new OrderPlacedMail($order));
        $order->forceFill(['email_sent' => true])->save();

        try {
            $crm->push($order);
        } catch (Throwable $exception) {
            Log::warning('CRM sync failed', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $order->id,
            ],
        ], 201);
    }

    public function destroy(Order $order): JsonResponse
    {
        $order->delete();

        return response()->json([
            'deleted' => true,
        ]);
    }
}
