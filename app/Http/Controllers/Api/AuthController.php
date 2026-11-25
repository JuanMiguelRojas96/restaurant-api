<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use OpenApi\Spec as OAS;

#[OAS\Schema(
    schema: 'UserWithToken',
    properties: [
        new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/User')),
        new OAS\Property(property: 'token', schema: new OAS\Schema(type: 'string', example: '1|hPQHfwEEDSK3loQG2XyD7MQjVXKxptPZfA8cXaTc')),
        new OAS\Property(property: 'token_type', schema: new OAS\Schema(type: 'string', example: 'Bearer')),
    ],
)]
class AuthController extends Controller
{
    /**
     * Register a new account. The role is never taken from the request: it is always "cliente".
     */
    #[OAS\Operation\Post(
        path: '/api/auth/register',
        operationId: 'register',
        summary: 'Create a customer account',
        description: 'Always creates a `cliente`: any `role` sent is ignored. Rate limited to 6 attempts per minute.',
        tags: ['Auth'],
        requestBody: new OAS\RequestBody(required: true, content: new OAS\MediaType\Json(ref: '#/components/schemas/RegisterInput')),
        responses: [
            new OAS\Response(response: 201, description: 'Account created, returns a token', content: new OAS\MediaType\Json(ref: '#/components/schemas/UserWithToken')),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OAS\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->userWithToken($user)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OAS\Operation\Post(
        path: '/api/auth/login',
        operationId: 'login',
        summary: 'Log in and get a token',
        description: 'Use one of the demo users (password: `password`). Rate limited to 6 attempts per minute.',
        tags: ['Auth'],
        requestBody: new OAS\RequestBody(required: true, content: new OAS\MediaType\Json(ref: '#/components/schemas/LoginInput')),
        responses: [
            new OAS\Response(response: 200, description: 'Logged in', content: new OAS\MediaType\Json(ref: '#/components/schemas/UserWithToken')),
            new OAS\Response(
                response: 401,
                description: 'Wrong email or password',
                content: new OAS\MediaType\Json(ref: '#/components/schemas/Error', example: ['message' => 'These credentials do not match our records.']),
            ),
            new OAS\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OAS\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => __('auth.failed')], Response::HTTP_UNAUTHORIZED);
        }

        return $this->userWithToken($user)->response();
    }

    /**
     * Revoke only the token used in this request (other devices stay logged in).
     */
    #[OAS\Operation\Post(
        path: '/api/auth/logout',
        operationId: 'logout',
        summary: 'Revoke the current token',
        description: 'Only the token used in this request stops working; other devices stay logged in.',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Auth'],
        responses: [
            new OAS\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ],
    )]
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    #[OAS\Operation\Get(
        path: '/api/auth/me',
        operationId: 'me',
        summary: 'Get the authenticated user',
        security: [new OAS\Security\Requirement(scheme: 'sanctum')],
        tags: ['Auth'],
        responses: [
            new OAS\Response(
                response: 200,
                description: 'The user who owns the token',
                content: new OAS\MediaType\Json(properties: [new OAS\Property(property: 'data', schema: new OAS\Schema(ref: '#/components/schemas/User'))]),
            ),
            new OAS\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ],
    )]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    private function userWithToken(User $user): UserResource
    {
        $token = $user->createToken('api-token')->plainTextToken;

        return (new UserResource($user))->additional([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
