<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->authService->login($request->validated(), $request->ip(), $request->userAgent()),
        ]);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->authService->refresh(
                $request->string('refresh_token')->toString(),
                $request->ip(),
                $request->userAgent(),
            ),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $context = $request->attributes->get('auth.context');

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $context['user']->id,
                    'name' => $context['user']->name,
                    'email' => $context['user']->email,
                    'role' => $context['user']->role->value,
                    'last_login_at' => $context['user']->last_login_at?->toIso8601String(),
                ],
                'session' => [
                    'id' => $context['session']->session_uuid,
                    'expires_at' => $context['session']->expires_at?->toIso8601String(),
                    'last_seen_at' => $context['session']->last_seen_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logoutUsingAccessToken(
            $request->bearerToken(),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([], 204);
    }

    public function inspect(Request $request): JsonResponse
    {
        $context = $request->attributes->get('auth.context');

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $context['user']->id,
                    'name' => $context['user']->name,
                    'email' => $context['user']->email,
                    'role' => $context['user']->role->value,
                ],
                'session' => [
                    'id' => $context['session']->session_uuid,
                    'expires_at' => $context['session']->expires_at?->toIso8601String(),
                ],
                'claims' => $context['claims'],
            ],
        ]);
    }
}
