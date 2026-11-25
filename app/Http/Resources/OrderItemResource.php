<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Spec as OAS;

/**
 * @mixin OrderItem
 */
#[OAS\Schema(
    schema: 'OrderItem',
    properties: [
        new OAS\Property(property: 'id', schema: new OAS\Schema(type: 'integer', example: 1)),
        new OAS\Property(property: 'dish_id', schema: new OAS\Schema(type: 'integer', example: 8)),
        new OAS\Property(property: 'dish_name', schema: new OAS\Schema(type: 'string', example: 'Limonada de coco')),
        new OAS\Property(property: 'quantity', schema: new OAS\Schema(type: 'integer', example: 2)),
        new OAS\Property(property: 'unit_price', schema: new OAS\Schema(type: 'number', format: 'float', example: 9500, description: 'Menu price copied when the item was added')),
        new OAS\Property(property: 'subtotal', schema: new OAS\Schema(type: 'number', format: 'float', example: 19000)),
        new OAS\Property(property: 'notes', schema: new OAS\Schema(type: 'string', nullable: true, example: 'sin hielo')),
    ],
)]
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dish_id' => $this->dish_id,
            'dish_name' => $this->whenLoaded('dish', fn () => $this->dish->name),
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->unit_price * $this->quantity,
            'notes' => $this->notes,
        ];
    }
}
