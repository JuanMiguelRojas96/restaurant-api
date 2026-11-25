<?php

namespace App\Http\Requests;

use App\Models\Table;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Table::class);
    }

    /**
     * New tables always start "libre": status is not accepted here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'integer', 'min:1', 'max:32767', 'unique:tables,number'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
