<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use OpenApi\Spec as OAS;

/**
 * Opening an order = choosing a table + the same item rules as adding items later.
 */
#[OAS\Schema(
    schema: 'OrderInput',
    required: ['table_id', 'items'],
    properties: [
        new OAS\Property(property: 'table_id', schema: new OAS\Schema(type: 'integer', example: 2, description: 'Must not have an active order')),
        new OAS\Property(property: 'items', schema: new OAS\Schema(type: 'array', minItems: 1, maxItems: 50, items: new OAS\Schema\Items(ref: '#/components/schemas/OrderItemInput'))),
    ],
    example: ['table_id' => 2, 'items' => [['dish_id' => 1, 'quantity' => 2], ['dish_id' => 8, 'quantity' => 1, 'notes' => 'sin hielo']]],
)]
class StoreOrderRequest extends AddOrderItemsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            ...parent::rules(),
        ];
    }
}
