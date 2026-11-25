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

class AuthController extends Controller
{
    /**
     * Register a new account. The role is never taken from the request: it is always "cliente".
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->userWithToken($user)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

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
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

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
