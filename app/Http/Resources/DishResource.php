<?php

namespace App\Http\Resources;

use App\Models\Dish;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dish
 */
class DishResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'available' => $this->available,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
