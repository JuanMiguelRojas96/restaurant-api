<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
