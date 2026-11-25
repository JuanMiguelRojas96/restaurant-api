<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Spec as OAS;

/**
 * @mixin Category
 */
#[OAS\Schema(
    schema: 'Category',
    properties: [
        new OAS\Property(property: 'id', schema: new OAS\Schema(type: 'integer', example: 3)),
        new OAS\Property(property: 'name', schema: new OAS\Schema(type: 'string', example: 'Bebidas')),
        new OAS\Property(property: 'dishes_count', schema: new OAS\Schema(type: 'integer', example: 4, description: 'Only in list and detail')),
    ],
)]
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'dishes_count' => $this->whenCounted('dishes'),
        ];
    }
}
