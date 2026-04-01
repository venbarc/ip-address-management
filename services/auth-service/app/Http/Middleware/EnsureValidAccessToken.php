<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthService;
use Closure;
use Illuminate\Http\Request;
use Throwable;

class EnsureValidAccessToken
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'message' => 'Authentication token is required.',
            ], 401);
        }

        try {
            $context = $this->authService->inspectAccessToken($token);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        }

        $request->attributes->set('auth.context', $context);
        $request->setUserResolver(fn () => $context['user']);

        return $next($request);
    }
}
