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
use OpenApi\Spec as OAS;

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
    #[OAS\Operation\Get(
        path: '/api/orders',
        operationId: 'listOrders',
        summary: 'List orders, newest first (staff)',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Orders'],
        parameters: [
            new OAS\Parameter\Query(name: 'status', schema: new OAS\Schema(type: 'string', enum: [OrderStatus::class])),
            new OAS\Parameter\Query(name: 'table_id', schema: new OAS\Schema(type: 'integer')),
            new OAS\Parameter\Query(name: 'page', schema: new OAS\Schema(type: 'integer', minimum: 1)),
            new OAS\Parameter\Query(name: 'per_page', description: 'Max 100', schema: new OAS\Schema(type: 'integer', default: 15, maximum: 100)),
        ],
        responses: [
            new OAS\Response(response: 200, description: 'Paginated orders (without items, with items_count)', content: new OAS\MediaType\Json(properties: [
                new OAS\Property(property: 'data', schema: new OAS\Schema(type: 'array', items: new OAS\Schema\Items(ref: '#/components/schemas/Order'))),
                new OAS\Property(property: 'links', schema: new OAS\Schema(ref: '#/components/schemas/PaginationLinks')),
                new OAS\Property(property: 'meta', schema: new OAS\Schema(ref: '#/components/schemas/PaginationMeta')),
            ])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
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

    #[OAS\Operation\Post(
        path: '/api/orders',
        operationId: 'openOrder',
        summary: 'Open an order on a table (admin, mesero)',
        description: <<<'MD'
            - Prices are copied from the menu and the total is calculated by the server (prices sent by the client are ignored).
            - Unavailable dishes are rejected (422).
            - The table must not have an active order (409); it becomes `ocupada`.
            MD,
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Orders'],
        requestBody: new OAS\RequestBody(required: true, content: new OAS\MediaType\Json(ref: '#/components/schemas/OrderInput')),
        responses: [
            new OAS\Response(response: 201, description: 'Order opened', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Order'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
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

    #[OAS\Operation\Get(
        path: '/api/orders/{order}',
        operationId: 'showOrder',
        summary: 'Get an order with its items (staff)',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Orders'],
        parameters: [new OAS\Parameter\Path(name: 'order', required: true, schema: new OAS\Schema(type: 'integer'), example: 1)],
        responses: [
            new OAS\Response(response: 200, description: 'The order', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Order'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return $this->detailed($order);
    }

    #[OAS\Operation\Patch(
        path: '/api/orders/{order}/status',
        operationId: 'changeOrderStatus',
        summary: 'Move an order to its next status',
        description: <<<'MD'
            | Next status | Who |
            |---|---|
            | en_preparacion, listo | cocina |
            | entregado, pagado, cancelado | admin, mesero |

            Valid moves only: pendiente → en_preparacion → listo → entregado → pagado; cancelado from pendiente or en_preparacion.
            Any other move is a 409. Paying or cancelling frees the table.
            MD,
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Orders'],
        parameters: [new OAS\Parameter\Path(name: 'order', required: true, schema: new OAS\Schema(type: 'integer'), example: 1)],
        requestBody: new OAS\RequestBody(required: true, content: new OAS\MediaType\Json(ref: '#/components/schemas/OrderStatusInput')),
        responses: [
            new OAS\Response(response: 200, description: 'Status changed', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Order'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OAS\Response(
                response: 409,
                description: 'The move does not exist in the lifecycle',
                content: new OAS\MediaType\Json(ref: '#/components/schemas/Error', example: ['message' => 'An order cannot go from pendiente to pagado.']),
            ),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        return $this->detailed($this->orders->changeStatus($order, $request->nextStatus()));
    }

    #[OAS\Operation\Post(
        path: '/api/orders/{order}/items',
        operationId: 'addOrderItems',
        summary: 'Add dishes to an open order (admin, mesero)',
        description: 'Only while the order is `pendiente` or `en_preparacion` (otherwise 409). The total is recalculated.',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Orders'],
        parameters: [new OAS\Parameter\Path(name: 'order', required: true, schema: new OAS\Schema(type: 'integer'), example: 1)],
        requestBody: new OAS\RequestBody(required: true, content: new OAS\MediaType\Json(ref: '#/components/schemas/OrderItemsInput')),
        responses: [
            new OAS\Response(response: 200, description: 'Items added', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Order'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OAS\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function addItems(AddOrderItemsRequest $request, Order $order): OrderResource
    {
        return $this->detailed($this->orders->addItems($order, $request->validated('items')));
    }

    private function detailed(Order $order): OrderResource
    {
        return new OrderResource($order->load(['table', 'user', 'items.dish']));
    }
}
