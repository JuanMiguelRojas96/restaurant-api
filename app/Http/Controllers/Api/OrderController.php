<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddOrderItemsRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Thin controller: validation lives in Form Requests, permissions in OrderPolicy
 * and the business rules in OrderService.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Filters: ?status=, ?table_id=. Newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        $filters = $request->validate([
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'table_id' => ['sometimes', 'integer'],
        ]);

        $orders = Order::with(['table', 'user'])
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['table_id'] ?? null, fn ($query, $tableId) => $query->where('table_id', $tableId))
            ->latest()
            ->paginate($this->perPage($request))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->open(
            $request->user(),
            $request->validated('table_id'),
            $request->validated('items'),
        );

        return $this->detailed($order)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return $this->detailed($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        return $this->detailed($this->orders->changeStatus($order, $request->nextStatus()));
    }

    public function addItems(AddOrderItemsRequest $request, Order $order): OrderResource
    {
        return $this->detailed($this->orders->addItems($order, $request->validated('items')));
    }

    private function detailed(Order $order): OrderResource
    {
        return new OrderResource($order->load(['table', 'user', 'items.dish']));
    }
}
