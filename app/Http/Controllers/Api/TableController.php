<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\UpdateTableStatusRequest;
use App\Http\Resources\TableResource;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TableController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Table::class);

        $tables = Table::orderBy('number')->paginate($this->perPage($request));

        return TableResource::collection($tables);
    }

    public function store(StoreTableRequest $request): JsonResponse
    {
        $table = Table::create($request->validated());

        return (new TableResource($table->refresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Table $table): TableResource
    {
        Gate::authorize('view', $table);

        return new TableResource($table);
    }

    /**
     * A table with an active order stays "ocupada": it is freed by paying or cancelling the order.
     */
    public function updateStatus(UpdateTableStatusRequest $request, Table $table): TableResource
    {
        if ($table->orders()->active()->exists()) {
            throw new BusinessRuleException("Table {$table->number} has an active order. Pay or cancel it to free the table.");
        }

        $table->update($request->validated());

        return new TableResource($table);
    }
}
