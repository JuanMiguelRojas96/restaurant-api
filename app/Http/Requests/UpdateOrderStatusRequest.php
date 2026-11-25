<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Spec as OAS;

#[OAS\Schema(
    schema: 'OrderStatusInput',
    required: ['status'],
    properties: [new OAS\Property(property: 'status', schema: new OAS\Schema(type: 'string', enum: [OrderStatus::class], example: 'en_preparacion'))],
)]
class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * The permission depends on the target status. An unknown status is let through
     * here so that rules() answers it with a 422 instead of a misleading 403.
     */
    public function authorize(): bool
    {
        $next = OrderStatus::tryFrom((string) $this->input('status'));

        return $next === null || $this->user()->can('updateStatus', [$this->route('order'), $next]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ];
    }

    public function nextStatus(): OrderStatus
    {
        return OrderStatus::from($this->validated('status'));
    }
}
