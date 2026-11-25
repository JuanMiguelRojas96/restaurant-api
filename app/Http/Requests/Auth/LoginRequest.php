<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Spec as OAS;

#[OAS\Schema(
    schema: 'LoginInput',
    required: ['email', 'password'],
    properties: [
        new OAS\Property(property: 'email', schema: new OAS\Schema(type: 'string', format: 'email', example: 'admin@restaurante.test')),
        new OAS\Property(property: 'password', schema: new OAS\Schema(type: 'string', format: 'password', example: 'password')),
    ],
)]
class LoginRequest extends FormRequest
{
    /**
     * Login is public: anyone may attempt it (rate limited at the route).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
