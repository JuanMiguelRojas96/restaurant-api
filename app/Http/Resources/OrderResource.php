<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Spec as OAS;

/**
 * @mixin Order
 */
#[OAS\Schema(
    schema: 'Order',
    properties: [
        new OAS\Property(property: 'id', schema: new OAS\Schema(type: 'integer', example: 16)),
        new OAS\Property(property: 'status', schema: new OAS\Schema(type: 'string', enum: [OrderStatus::class], example: 'pendiente')),
        new OAS\Property(property: 'total', schema: new OAS\Schema(type: 'number', format: 'float', example: 31000, description: 'Calculated by the server')),
        new OAS\Property(property: 'table', schema: new OAS\Schema(ref: '#/components/schemas/Table')),
        new OAS\Property(
            property: 'waiter',
            schema: new OAS\Schema(properties: [
                new OAS\Property(property: 'id', schema: new OAS\Schema(type: 'integer', example: 2)),
                new OAS\Property(property: 'name', schema: new OAS\Schema(type: 'string', example: 'Mesero Demo')),
            ], type: 'object'),
        ),
        new OAS\Property(property: 'items_count', schema: new OAS\Schema(type: 'integer', example: 2, description: 'Only in the list')),
        new OAS\Property(property: 'items', schema: new OAS\Schema(type: 'array', items: new OAS\Schema\Items(ref: '#/components/schemas/OrderItem'), description: 'Only in the detail')),
        new OAS\Property(property: 'created_at', schema: new OAS\Schema(type: 'string', format: 'date-time')),
        new OAS\Property(property: 'updated_at', schema: new OAS\Schema(type: 'string', format: 'date-time')),
    ],
)]
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'total' => (float) $this->total,
            'table' => new TableResource($this->whenLoaded('table')),
            'waiter' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'items_count' => $this->whenCounted('items'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
