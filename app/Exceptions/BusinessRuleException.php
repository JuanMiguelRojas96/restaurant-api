<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A request that is valid in shape but conflicts with the current state of the restaurant
 * (e.g. opening an order on an occupied table). Rendered as 409 Conflict.
 */
class BusinessRuleException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], Response::HTTP_CONFLICT);
    }
}
