<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every API client is treated as a JSON client, even if it forgets the Accept header,
 * so errors (401, 404, 500...) are always returned as JSON instead of redirects or HTML.
 */
class ForceJsonResponse
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
