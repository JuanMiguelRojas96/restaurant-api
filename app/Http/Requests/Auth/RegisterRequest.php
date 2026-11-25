<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use OpenApi\Spec as OAS;

#[OAS\Schema(
    schema: 'RegisterInput',
    required: ['name', 'email', 'password', 'password_confirmation'],
    properties: [
        new OAS\Property(property: 'name', schema: new OAS\Schema(type: 'string', maxLength: 255, example: 'Ana Gómez')),
        new OAS\Property(property: 'email', schema: new OAS\Schema(type: 'string', format: 'email', example: 'ana@example.com')),
        new OAS\Property(property: 'password', schema: new OAS\Schema(type: 'string', format: 'password', minLength: 8, example: 'secret123', description: 'At least 8 characters, letters and numbers')),
        new OAS\Property(property: 'password_confirmation', schema: new OAS\Schema(type: 'string', format: 'password', example: 'secret123')),
    ],
)]
class RegisterRequest extends FormRequest
{
    /**
     * Registration is public: anyone may create a customer account.
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
