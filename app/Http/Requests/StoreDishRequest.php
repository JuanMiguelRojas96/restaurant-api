<?php

namespace App\Http\Requests;

use App\Models\Dish;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
