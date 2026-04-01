<?php

namespace App\Http\Middleware;

use App\Services\Gateway\AuthServiceClient;
use App\Support\ActorContext;
use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Throwable;

class ResolveGatewayUser
{
    public function __construct(
        private readonly AuthServiceClient $authServiceClient,
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
            $payload = $this->authServiceClient->inspectAccessToken($token);
            $actor = ActorContext::fromInspectPayload($payload);
        } catch (RequestException $exception) {
            return response()->json([
                'message' => $exception->response?->json('message') ?? 'Authentication failed.',
            ], $exception->response?->status() ?? 401);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Authentication failed.',
            ], 401);
        }

        $request->attributes->set('actor', $actor);
        $request->attributes->set('auth.inspect', $payload);

        return $next($request);
    }
}
