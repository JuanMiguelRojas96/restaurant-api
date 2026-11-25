<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Spec as OAS;

#[OAS\Schema(
    schema: 'OrderItemInput',
    required: ['dish_id', 'quantity'],
    properties: [
        new OAS\Property(property: 'dish_id', schema: new OAS\Schema(type: 'integer', example: 8, description: 'Must be available')),
        new OAS\Property(property: 'quantity', schema: new OAS\Schema(type: 'integer', minimum: 1, maximum: 50, example: 2)),
        new OAS\Property(property: 'notes', schema: new OAS\Schema(type: 'string', maxLength: 255, nullable: true, example: 'sin hielo')),
    ],
)]
#[OAS\Schema(
    schema: 'OrderItemsInput',
    required: ['items'],
    properties: [
        new OAS\Property(property: 'items', schema: new OAS\Schema(type: 'array', minItems: 1, maxItems: 50, items: new OAS\Schema\Items(ref: '#/components/schemas/OrderItemInput'))),
    ],
    example: ['items' => [['dish_id' => 5, 'quantity' => 1]]],
)]
class AddOrderItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addItems', $this->route('order'));
    }

    /**
     * Prices are never accepted from the client: they are copied from the menu.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.dish_id' => ['required', 'integer', Rule::exists('dishes', 'id')->where('available', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.dish_id.exists' => 'The dish does not exist or is not available.',
        ];
    }
}
