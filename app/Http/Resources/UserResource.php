<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Spec as OAS;

/**
 * @mixin User
 */
#[OAS\Schema(
    schema: 'User',
    properties: [
        new OAS\Property(property: 'id', schema: new OAS\Schema(type: 'integer', example: 1)),
        new OAS\Property(property: 'name', schema: new OAS\Schema(type: 'string', example: 'Admin Demo')),
        new OAS\Property(property: 'email', schema: new OAS\Schema(type: 'string', format: 'email', example: 'admin@restaurante.test')),
        new OAS\Property(property: 'role', schema: new OAS\Schema(type: 'string', enum: [Role::class], example: 'admin')),
        new OAS\Property(property: 'created_at', schema: new OAS\Schema(type: 'string', format: 'date-time')),
    ],
)]
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
