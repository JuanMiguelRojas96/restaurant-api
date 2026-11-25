<?php

namespace App\Http\Resources;

use App\Models\Dish;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Spec as OAS;

/**
 * @mixin Dish
 */
#[OAS\Schema(
    schema: 'Dish',
    properties: [
        new OAS\Property(property: 'id', schema: new OAS\Schema(type: 'integer', example: 8)),
        new OAS\Property(property: 'name', schema: new OAS\Schema(type: 'string', example: 'Limonada de coco')),
        new OAS\Property(property: 'description', schema: new OAS\Schema(type: 'string', nullable: true, example: 'Limonada natural con crema de coco')),
        new OAS\Property(property: 'price', schema: new OAS\Schema(type: 'number', format: 'float', example: 9500)),
        new OAS\Property(property: 'available', schema: new OAS\Schema(type: 'boolean', example: true)),
        new OAS\Property(property: 'category_id', schema: new OAS\Schema(type: 'integer', example: 3)),
        new OAS\Property(property: 'category', schema: new OAS\Schema(ref: '#/components/schemas/Category')),
    ],
)]
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
