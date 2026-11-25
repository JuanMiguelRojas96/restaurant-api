<?php

namespace App\Http\Resources;

use App\Enums\TableStatus;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Spec as OAS;

/**
 * @mixin Table
 */
#[OAS\Schema(
    schema: 'Table',
    properties: [
        new OAS\Property(property: 'id', schema: new OAS\Schema(type: 'integer', example: 5)),
        new OAS\Property(property: 'number', schema: new OAS\Schema(type: 'integer', example: 5)),
        new OAS\Property(property: 'capacity', schema: new OAS\Schema(type: 'integer', example: 4)),
        new OAS\Property(property: 'status', schema: new OAS\Schema(type: 'string', enum: [TableStatus::class], example: 'libre')),
    ],
)]
class TableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'capacity' => $this->capacity,
            'status' => $this->status->value,
        ];
    }
}
