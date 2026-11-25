<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDishRequest;
use App\Http\Requests\UpdateDishRequest;
use App\Http\Resources\DishResource;
use App\Models\Dish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use OpenApi\Spec as OAS;

class DishController extends Controller
{
    /**
     * Filters: ?category_id=, ?available=true|false, ?search= (name or description).
     */
    #[OAS\Operation\Get(
        path: '/api/dishes',
        operationId: 'listDishes',
        summary: 'List the menu with filters',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Dishes'],
        parameters: [
            new OAS\Parameter\Query(name: 'category_id', schema: new OAS\Schema(type: 'integer'), example: 3),
            new OAS\Parameter\Query(name: 'available', schema: new OAS\Schema(type: 'string', enum: ['true', 'false'])),
            new OAS\Parameter\Query(name: 'search', description: 'Searches name and description, case insensitive', schema: new OAS\Schema(type: 'string', maxLength: 100), example: 'leche'),
            new OAS\Parameter\Query(name: 'page', schema: new OAS\Schema(type: 'integer', minimum: 1)),
            new OAS\Parameter\Query(name: 'per_page', description: 'Max 100', schema: new OAS\Schema(type: 'integer', default: 15, maximum: 100)),
        ],
        responses: [
            new OAS\Response(response: 200, description: 'Paginated dishes', content: new OAS\MediaType\Json(properties: [
                new OAS\Property(property: 'data', schema: new OAS\Schema(type: 'array', items: new OAS\Schema\Items(ref: '#/components/schemas/Dish'))),
                new OAS\Property(property: 'links', schema: new OAS\Schema(ref: '#/components/schemas/PaginationLinks')),
                new OAS\Property(property: 'meta', schema: new OAS\Schema(ref: '#/components/schemas/PaginationMeta')),
            ])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Dish::class);

        $filters = $request->validate([
            'category_id' => ['sometimes', 'integer'],
            'available' => ['sometimes', 'in:true,false,1,0'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $dishes = Dish::with('category')
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when(isset($filters['available']), fn ($query) => $query->where('available', $request->boolean('available')))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereAny(['name', 'description'], 'ilike', "%{$search}%"))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return DishResource::collection($dishes);
    }

    #[OAS\Operation\Post(
        path: '/api/dishes',
        operationId: 'createDish',
        summary: 'Add a dish to the menu (admin)',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Dishes'],
        requestBody: new OAS\RequestBody(required: true, content: new OAS\MediaType\Json(ref: '#/components/schemas/DishInput')),
        responses: [
            new OAS\Response(response: 201, description: 'Created', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Dish'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function store(StoreDishRequest $request): JsonResponse
    {
        $dish = Dish::create($request->validated());

        return (new DishResource($dish->load('category')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OAS\Operation\Get(
        path: '/api/dishes/{dish}',
        operationId: 'showDish',
        summary: 'Get a dish',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Dishes'],
        parameters: [new OAS\Parameter\Path(name: 'dish', required: true, schema: new OAS\Schema(type: 'integer'), example: 8)],
        responses: [
            new OAS\Response(response: 200, description: 'The dish', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Dish'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Dish $dish): DishResource
    {
        Gate::authorize('view', $dish);

        return new DishResource($dish->load('category'));
    }

    #[OAS\Operation\Put(
        path: '/api/dishes/{dish}',
        operationId: 'updateDish',
        summary: 'Replace a dish (admin)',
        description: 'Use `"available": false` to take a dish off the menu without deleting it.',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Dishes'],
        parameters: [new OAS\Parameter\Path(name: 'dish', required: true, schema: new OAS\Schema(type: 'integer'), example: 8)],
        requestBody: new OAS\RequestBody(required: true, content: new OAS\MediaType\Json(ref: '#/components/schemas/DishInput')),
        responses: [
            new OAS\Response(response: 200, description: 'Updated', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Dish'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function update(UpdateDishRequest $request, Dish $dish): DishResource
    {
        $dish->update($request->validated());

        return new DishResource($dish->load('category'));
    }

    /**
     * Dishes already sold are kept for sales history: mark them unavailable instead.
     */
    #[OAS\Operation\Delete(
        path: '/api/dishes/{dish}',
        operationId: 'deleteDish',
        summary: 'Delete a dish that was never sold (admin)',
        description: 'Fails with 409 if the dish appears in any order: set `available` to false instead.',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Dishes'],
        parameters: [new OAS\Parameter\Path(name: 'dish', required: true, schema: new OAS\Schema(type: 'integer'))],
        responses: [
            new OAS\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OAS\Response(response: 409, ref: '#/components/responses/Conflict'),
        ],
    )]
    public function destroy(Dish $dish): Response
    {
        Gate::authorize('delete', $dish);

        abort_if(
            $dish->orderItems()->exists(),
            Response::HTTP_CONFLICT,
            'The dish appears in orders. Set "available" to false instead of deleting it.',
        );

        $dish->delete();

        return response()->noContent();
    }
}
