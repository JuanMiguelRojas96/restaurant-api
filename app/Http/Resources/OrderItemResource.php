<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
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
