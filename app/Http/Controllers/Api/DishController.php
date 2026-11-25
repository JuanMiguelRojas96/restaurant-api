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

class DishController extends Controller
{
    /**
     * Filters: ?category_id=, ?available=true|false, ?search= (name or description).
     */
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

    public function store(StoreDishRequest $request): JsonResponse
    {
        $dish = Dish::create($request->validated());

        return (new DishResource($dish->load('category')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Dish $dish): DishResource
    {
        Gate::authorize('view', $dish);

        return new DishResource($dish->load('category'));
    }

    public function update(UpdateDishRequest $request, Dish $dish): DishResource
    {
        $dish->update($request->validated());

        return new DishResource($dish->load('category'));
    }

    /**
     * Dishes already sold are kept for sales history: mark them unavailable instead.
     */
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
