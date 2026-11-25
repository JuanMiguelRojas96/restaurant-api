<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Spec as OAS;

#[OAS\Schema(
    schema: 'CategoryInput',
    required: ['name'],
    properties: [new OAS\Property(property: 'name', schema: new OAS\Schema(type: 'string', maxLength: 100, example: 'Mariscos', description: 'Unique'))],
)]
class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Category::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
        ];
    }
}
