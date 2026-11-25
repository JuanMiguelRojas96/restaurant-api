<?php

namespace App\OpenApi;

use OpenApi\Spec as OAS;

/**
 * Global OpenAPI definition: API info, auth scheme, tags and the error responses
 * shared by every endpoint. Endpoint docs live next to their controller methods.
 */
#[OAS\Info(
    version: '1.0.0',
    title: 'Restaurant API',
    description: <<<'MD'
        REST API to run a restaurant: menu, tables, orders and role-based staff.

        **How to try it**
        1. `POST /api/auth/login` with one of the demo users below (password: `password`).
        2. Copy the `token` from the response.
        3. Click **Authorize** (top right), paste the token and confirm.

        | Email | Role | Can |
        |---|---|---|
        | admin@restaurante.test | admin | manage menu and tables, everything a waiter can |
        | mesero@restaurante.test | mesero | open orders, add items, deliver, charge, cancel |
        | cocina@restaurante.test | cocina | move orders to *en_preparacion* and *listo* |
        | cliente@restaurante.test | cliente | browse the menu |

        **Errors** always return `{"message": "..."}`; validation errors (422) add `errors` per field.
        MD,
)]
#[OAS\Server(url: '/', description: 'This server')]
#[OAS\Security\Scheme(
    securityScheme: 'sanctum',
    type: 'http',
    description: 'Paste the token returned by /api/auth/login (without the "Bearer " prefix).',
    scheme: 'bearer',
)]
#[OAS\Tag(name: 'Auth', description: 'Register, log in and manage the session token')]
#[OAS\Tag(name: 'Categories', description: 'Menu categories (admin manages, everyone reads)')]
#[OAS\Tag(name: 'Dishes', description: 'Menu dishes with filters (admin manages, everyone reads)')]
#[OAS\Tag(name: 'Tables', description: 'Restaurant tables (staff only)')]
#[OAS\Tag(name: 'Orders', description: 'Order lifecycle: pendiente → en_preparacion → listo → entregado → pagado (or cancelado)')]
#[OAS\Schema(
    schema: 'Error',
    required: ['message'],
    properties: [new OAS\Property(property: 'message', schema: new OAS\Schema(type: 'string', example: 'This action is unauthorized.'))],
)]
#[OAS\Schema(
    schema: 'ValidationError',
    required: ['message', 'errors'],
    properties: [
        new OAS\Property(property: 'message', schema: new OAS\Schema(type: 'string', example: 'The name field is required.')),
        new OAS\Property(
            property: 'errors',
            schema: new OAS\Schema(type: 'object', example: ['name' => ['The name field is required.']], additionalProperties: new OAS\Schema\AdditionalProperties(type: 'array', items: new OAS\Schema\Items(type: 'string'))),
        ),
    ],
)]
#[OAS\Schema(
    schema: 'PaginationLinks',
    properties: [
        new OAS\Property(property: 'first', schema: new OAS\Schema(type: 'string', example: 'http://localhost:8000/api/dishes?page=1')),
        new OAS\Property(property: 'last', schema: new OAS\Schema(type: 'string', example: 'http://localhost:8000/api/dishes?page=3')),
        new OAS\Property(property: 'prev', schema: new OAS\Schema(type: 'string', nullable: true)),
        new OAS\Property(property: 'next', schema: new OAS\Schema(type: 'string', nullable: true)),
    ],
)]
#[OAS\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OAS\Property(property: 'current_page', schema: new OAS\Schema(type: 'integer', example: 1)),
        new OAS\Property(property: 'last_page', schema: new OAS\Schema(type: 'integer', example: 3)),
        new OAS\Property(property: 'per_page', schema: new OAS\Schema(type: 'integer', example: 15)),
        new OAS\Property(property: 'total', schema: new OAS\Schema(type: 'integer', example: 40)),
    ],
)]
#[OAS\Response(
    response: 'Unauthenticated',
    description: 'Missing or invalid token',
    content: new OAS\MediaType\Json(ref: '#/components/schemas/Error', example: ['message' => 'Unauthenticated.']),
)]
#[OAS\Response(
    response: 'Forbidden',
    description: 'Your role is not allowed to do this',
    content: new OAS\MediaType\Json(ref: '#/components/schemas/Error', example: ['message' => 'This action is unauthorized.']),
)]
#[OAS\Response(
    response: 'NotFound',
    description: 'The resource does not exist',
    content: new OAS\MediaType\Json(ref: '#/components/schemas/Error', example: ['message' => 'Dish not found.']),
)]
#[OAS\Response(
    response: 'Conflict',
    description: 'Valid request that conflicts with the current state (business rule)',
    content: new OAS\MediaType\Json(ref: '#/components/schemas/Error', example: ['message' => 'Table 5 already has an active order.']),
)]
#[OAS\Response(
    response: 'ValidationError',
    description: 'Invalid input',
    content: new OAS\MediaType\Json(ref: '#/components/schemas/ValidationError'),
)]
#[OAS\Response(
    response: 'TooManyRequests',
    description: 'Too many attempts (6 per minute)',
    content: new OAS\MediaType\Json(ref: '#/components/schemas/Error', example: ['message' => 'Too Many Attempts.']),
)]
#[OAS\Response(response: 'NoContent', description: 'Done, nothing to return')]
final class OpenApiSpec
{
}
