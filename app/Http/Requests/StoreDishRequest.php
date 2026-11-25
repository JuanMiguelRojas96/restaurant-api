<?php

namespace App\Http\Requests;

use App\Models\Dish;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Spec as OAS;

#[OAS\Schema(
    schema: 'DishInput',
    required: ['category_id', 'name', 'price'],
    properties: [
        new OAS\Property(property: 'category_id', schema: new OAS\Schema(type: 'integer', example: 2)),
        new OAS\Property(property: 'name', schema: new OAS\Schema(type: 'string', maxLength: 150, example: 'Cazuela de mariscos')),
        new OAS\Property(property: 'description', schema: new OAS\Schema(type: 'string', maxLength: 1000, nullable: true, example: 'Cazuela con leche de coco y patacón')),
        new OAS\Property(property: 'price', schema: new OAS\Schema(type: 'number', format: 'float', minimum: 0, example: 42000, description: 'Up to 2 decimals')),
        new OAS\Property(property: 'available', schema: new OAS\Schema(type: 'boolean', example: true, description: 'Defaults to true')),
    ],
)]
class StoreDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Dish::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'available' => ['sometimes', 'boolean'],
        ];
    }
}
