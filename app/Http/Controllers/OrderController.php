<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Http\Requests\ListOrdersRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(ListOrdersRequest $request, OrderRepository $orders): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $orders->list($request->status(), $request->customerEmail(), $request->perPage()),
        );
    }

    public function store(StoreOrderRequest $request, OrderRepository $orders, #[CurrentUser] User $creator): JsonResponse
    {
        $order = $orders->create($request->customerId(), $request->items(), $creator);

        OrderPlaced::dispatch($order);

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function destroy(Order $order, OrderRepository $orders): JsonResponse
    {
        Gate::authorize('delete', $order);

        $orders->delete($order);

        return response()->json([
            'deleted' => true,
        ]);
    }
}
