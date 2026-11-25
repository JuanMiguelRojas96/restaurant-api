<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use OpenApi\Spec as OAS;

class CategoryController extends Controller
{
    #[OAS\Operation\Get(
        path: '/api/categories',
        operationId: 'listCategories',
        summary: 'List categories with their number of dishes',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Categories'],
        parameters: [
            new OAS\Parameter\Query(name: 'page', schema: new OAS\Schema(type: 'integer', minimum: 1)),
            new OAS\Parameter\Query(name: 'per_page', description: 'Max 100', schema: new OAS\Schema(type: 'integer', default: 15, maximum: 100)),
        ],
        responses: [
            new OAS\Response(response: 200, description: 'Paginated categories', content: new OAS\MediaType\Json(properties: [
                new OAS\Property(property: 'data', schema: new OAS\Schema(type: 'array', items: new OAS\Schema\Items(ref: '#/components/schemas/Category'))),
                new OAS\Property(property: 'links', schema: new OAS\Schema(ref: '#/components/schemas/PaginationLinks')),
                new OAS\Property(property: 'meta', schema: new OAS\Schema(ref: '#/components/schemas/PaginationMeta')),
            ])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Category::class);

        $categories = Category::withCount('dishes')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return CategoryResource::collection($categories);
    }

    #[OAS\Operation\Post(
        path: '/api/categories',
        operationId: 'createCategory',
        summary: 'Create a category (admin)',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Categories'],
        requestBody: new OAS\RequestBody(required: true, content: new OAS\MediaType\Json(ref: '#/components/schemas/CategoryInput')),
        responses: [
            new OAS\Response(response: 201, description: 'Created', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Category'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OAS\Operation\Get(
        path: '/api/categories/{category}',
        operationId: 'showCategory',
        summary: 'Get a category',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Categories'],
        parameters: [new OAS\Parameter\Path(name: 'category', required: true, schema: new OAS\Schema(type: 'integer'), example: 3)],
        responses: [
            new OAS\Response(response: 200, description: 'The category', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Category'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Category $category): CategoryResource
    {
        Gate::authorize('view', $category);

        return new CategoryResource($category->loadCount('dishes'));
    }

    #[OAS\Operation\Put(
        path: '/api/categories/{category}',
        operationId: 'updateCategory',
        summary: 'Rename a category (admin)',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Categories'],
        parameters: [new OAS\Parameter\Path(name: 'category', required: true, schema: new OAS\Schema(type: 'integer'), example: 3)],
        requestBody: new OAS\RequestBody(required: true, content: new OAS\MediaType\Json(ref: '#/components/schemas/CategoryInput')),
        responses: [
            new OAS\Response(response: 200, description: 'Updated', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Category'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category);
    }

    /**
     * A category with dishes cannot be deleted: move or delete its dishes first.
     */
    #[OAS\Operation\Delete(
        path: '/api/categories/{category}',
        operationId: 'deleteCategory',
        summary: 'Delete an empty category (admin)',
        description: 'Fails with 409 if the category still has dishes.',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Categories'],
        parameters: [new OAS\Parameter\Path(name: 'category', required: true, schema: new OAS\Schema(type: 'integer'))],
        responses: [
            new OAS\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OAS\Response(response: 409, ref: '#/components/responses/Conflict'),
        ],
    )]
    public function destroy(Category $category): Response
    {
        Gate::authorize('delete', $category);

        abort_if(
            $category->dishes()->exists(),
            Response::HTTP_CONFLICT,
            'The category has dishes. Move or delete them before deleting the category.',
        );

        $category->delete();

        return response()->noContent();
    }
}
