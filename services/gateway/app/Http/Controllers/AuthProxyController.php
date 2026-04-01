<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RelaysUpstreamResponses;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Services\Gateway\AuthServiceClient;
use App\Services\Gateway\GatewayRequestLogger;
use App\Support\ActorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthProxyController extends Controller
{
    use RelaysUpstreamResponses;

    public function __construct(
        private readonly AuthServiceClient $authServiceClient,
        private readonly GatewayRequestLogger $requestLogger,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->relay(
            request: $request,
            logger: $this->requestLogger,
            upstream: 'auth-service',
            callback: fn () => $this->authServiceClient->login($request->validated()),
        );
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        return $this->relay(
            request: $request,
            logger: $this->requestLogger,
            upstream: 'auth-service',
            callback: fn () => $this->authServiceClient->refresh($request->validated()),
        );
    }

    public function me(Request $request): JsonResponse
    {
        /** @var ActorContext $actor */
        $actor = $request->attributes->get('actor');
        $payload = $request->attributes->get('auth.inspect');

        $this->requestLogger->record($request, 'auth-service', 200, $actor);

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var ActorContext $actor */
        $actor = $request->attributes->get('actor');

        return $this->relay(
            request: $request,
            logger: $this->requestLogger,
            upstream: 'auth-service',
            callback: fn () => $this->authServiceClient->logout((string) $request->bearerToken()),
            actor: $actor,
        );
    }
}
