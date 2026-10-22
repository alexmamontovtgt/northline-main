<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListOrdersRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\CrmSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderController extends Controller
{
    public function index(ListOrdersRequest $request, OrderRepository $orders): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $orders->list($request->status(), $request->customerEmail(), $request->perPage()),
        );
    }

    public function store(StoreOrderRequest $request, OrderRepository $orders, CrmSyncService $crm): JsonResponse
    {
        $order = $orders->create($request->customerId(), $request->items(), $request->creator());

        $customer = $order->customer ?? throw new \RuntimeException('Order is missing its customer.');

        Mail::to($customer->email)->send(new OrderPlacedMail($order));

        $orders->markEmailSent($order);

        try {
            $crm->push($order);
        } catch (Throwable $exception) {
            Log::warning('CRM sync failed', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function destroy(Order $order): JsonResponse
    {
        Gate::authorize('delete', $order);

        $order->delete();

        return response()->json([
            'deleted' => true,
        ]);
    }
}
