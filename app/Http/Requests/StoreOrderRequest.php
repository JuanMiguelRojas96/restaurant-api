<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Opening an order = choosing a table + the same item rules as adding items later.
 */
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
