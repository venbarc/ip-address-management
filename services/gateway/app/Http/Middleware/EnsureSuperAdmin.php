<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $actor = $request->attributes->get('actor');

        if (! $actor || ! $actor->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only super-admins can access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
