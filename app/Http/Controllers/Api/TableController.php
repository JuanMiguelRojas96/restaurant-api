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
use OpenApi\Spec as OAS;

class TableController extends Controller
{
    #[OAS\Operation\Get(
        path: '/api/tables',
        operationId: 'listTables',
        summary: 'List tables (staff)',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Tables'],
        parameters: [
            new OAS\Parameter\Query(name: 'page', schema: new OAS\Schema(type: 'integer', minimum: 1)),
            new OAS\Parameter\Query(name: 'per_page', description: 'Max 100', schema: new OAS\Schema(type: 'integer', default: 15, maximum: 100)),
        ],
        responses: [
            new OAS\Response(response: 200, description: 'Paginated tables', content: new OAS\MediaType\Json(properties: [
                new OAS\Property(property: 'data', schema: new OAS\Schema(type: 'array', items: new OAS\Schema\Items(ref: '#/components/schemas/Table'))),
                new OAS\Property(property: 'links', schema: new OAS\Schema(ref: '#/components/schemas/PaginationLinks')),
                new OAS\Property(property: 'meta', schema: new OAS\Schema(ref: '#/components/schemas/PaginationMeta')),
            ])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Table::class);

        $tables = Table::orderBy('number')->paginate($this->perPage($request));

        return TableResource::collection($tables);
    }

    #[OAS\Operation\Post(
        path: '/api/tables',
        operationId: 'createTable',
        summary: 'Create a table (admin)',
        description: 'New tables always start as `libre`.',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Tables'],
        requestBody: new OAS\RequestBody(required: true, content: new OAS\MediaType\Json(ref: '#/components/schemas/TableInput')),
        responses: [
            new OAS\Response(response: 201, description: 'Created', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Table'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function store(StoreTableRequest $request): JsonResponse
    {
        $table = Table::create($request->validated());

        return (new TableResource($table->refresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OAS\Operation\Get(
        path: '/api/tables/{table}',
        operationId: 'showTable',
        summary: 'Get a table (staff)',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Tables'],
        parameters: [new OAS\Parameter\Path(name: 'table', required: true, schema: new OAS\Schema(type: 'integer'), example: 5)],
        responses: [
            new OAS\Response(response: 200, description: 'The table', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Table'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Table $table): TableResource
    {
        Gate::authorize('view', $table);

        return new TableResource($table);
    }

    /**
     * A table with an active order stays "ocupada": it is freed by paying or cancelling the order.
     */
    #[OAS\Operation\Patch(
        path: '/api/tables/{table}/status',
        operationId: 'updateTableStatus',
        summary: 'Change a table status (admin, mesero)',
        description: 'A table with an active order cannot be changed (409): pay or cancel the order to free it.',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Tables'],
        parameters: [new OAS\Parameter\Path(name: 'table', required: true, schema: new OAS\Schema(type: 'integer'), example: 5)],
        requestBody: new OAS\RequestBody(required: true, content: new OAS\MediaType\Json(ref: '#/components/schemas/TableStatusInput')),
        responses: [
            new OAS\Response(response: 200, description: 'Updated', content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/Table'))])),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OAS\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OAS\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OAS\Response(response: 409, ref: '#/components/responses/Conflict'),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function updateStatus(UpdateTableStatusRequest $request, Table $table): TableResource
    {
        if ($table->orders()->active()->exists()) {
            throw new BusinessRuleException("Table {$table->number} has an active order. Pay or cancel it to free the table.");
        }

        $table->update($request->validated());

        return new TableResource($table);
    }
}
