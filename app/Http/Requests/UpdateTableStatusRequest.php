<?php

namespace App\Http\Requests;

use App\Enums\TableStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Spec as OAS;

#[OAS\Schema(
    schema: 'TableStatusInput',
    required: ['status'],
    properties: [new OAS\Property(property: 'status', schema: new OAS\Schema(type: 'string', enum: [TableStatus::class], example: 'reservada'))],
)]
class UpdateTableStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('table'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TableStatus::class)],
        ];
    }
}
