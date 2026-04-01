<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Internal-Secret') !== config('security.internal_service_secret')) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        return $next($request);
    }
}
