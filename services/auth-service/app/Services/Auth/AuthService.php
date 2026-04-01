<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserSession;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthService
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function login(array $attributes, ?string $requestIp, ?string $userAgent): array
    {
        $user = User::query()->where('email', $attributes['email'])->first();

        if (! $user || ! Hash::check($attributes['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are invalid.',
            ]);
        }

        return DB::transaction(function () use ($user, $requestIp, $userAgent): array {
            $plainRefreshToken = Str::random(80);
            $session = UserSession::query()->create([
                'user_id' => $user->id,
                'session_uuid' => (string) Str::ulid(),
                'refresh_token_hash' => hash('sha256', $plainRefreshToken),
                'login_ip' => $requestIp,
                'user_agent' => $userAgent,
                'last_seen_at' => now(),
                'expires_at' => now()->addDays(config('security.refresh_token_ttl_days')),
            ]);

            $user->forceFill([
                'last_login_at' => now(),
            ])->save();

            $this->auditLogger->record(
                event: 'auth.login',
                actor: $user,
                sessionUuid: $session->session_uuid,
                subjectType: 'session',
                subjectId: $session->session_uuid,
                context: [
                    'message' => 'User logged in.',
                ],
                requestIp: $requestIp,
                userAgent: $userAgent,
            );

            return $this->buildAuthPayload($user, $session, $plainRefreshToken);
        });
    }

    public function refresh(string $refreshToken, ?string $requestIp, ?string $userAgent): array
    {
        $session = $this->findSessionByRefreshToken($refreshToken);

        if (! $session->isActive()) {
            throw ValidationException::withMessages([
                'refresh_token' => 'The refresh token is no longer active.',
            ]);
        }

        $plainRefreshToken = Str::random(80);

        return DB::transaction(function () use ($session, $plainRefreshToken, $requestIp, $userAgent): array {
            $session->forceFill([
                'refresh_token_hash' => hash('sha256', $plainRefreshToken),
                'last_seen_at' => now(),
                'expires_at' => now()->addDays(config('security.refresh_token_ttl_days')),
                'login_ip' => $requestIp ?: $session->login_ip,
                'user_agent' => $userAgent ?: $session->user_agent,
            ])->save();

            $user = $session->user;

            $this->auditLogger->record(
                event: 'auth.refresh',
                actor: $user,
                sessionUuid: $session->session_uuid,
                subjectType: 'session',
                subjectId: $session->session_uuid,
                context: [
                    'message' => 'Access token refreshed.',
                ],
                requestIp: $requestIp,
                userAgent: $userAgent,
            );

            return $this->buildAuthPayload($user, $session, $plainRefreshToken);
        });
    }

    public function logoutUsingAccessToken(string $token, ?string $requestIp, ?string $userAgent): void
    {
        $context = $this->inspectAccessToken($token);
        $this->revokeSession($context['session'], $context['user'], $requestIp, $userAgent);
    }

    public function inspectAccessToken(string $token): array
    {
        $claims = $this->jwtService->decode($token);
        $sessionId = $claims['sid'] ?? null;

        if (! is_string($sessionId) || $sessionId === '') {
            throw new RuntimeException('Token is missing the session identifier.');
        }

        $session = UserSession::query()
            ->with('user')
            ->where('session_uuid', $sessionId)
            ->first();

        if (! $session || ! $session->user) {
            throw new RuntimeException('Session could not be resolved.');
        }

        if ((string) $session->user_id !== (string) ($claims['sub'] ?? '')) {
            throw new RuntimeException('Token subject does not match the session user.');
        }

        if (! $session->isActive()) {
            throw new RuntimeException('Session is no longer active.');
        }

        $session->forceFill([
            'last_seen_at' => now(),
        ])->save();

        return [
            'claims' => $claims,
            'session' => $session,
            'user' => $session->user,
        ];
    }

    private function buildAuthPayload(User $user, UserSession $session, string $plainRefreshToken): array
    {
        $accessToken = $this->jwtService->issueAccessToken($user, $session);

        return [
            'user' => $this->serializeUser($user),
            'session_id' => $session->session_uuid,
            'access_token' => $accessToken['token'],
            'token_type' => 'Bearer',
            'expires_at' => $accessToken['expires_at']->toIso8601String(),
            'expires_in' => now()->diffInSeconds($accessToken['expires_at']),
            'refresh_token' => $plainRefreshToken,
            'refresh_token_expires_at' => $session->expires_at?->toIso8601String(),
        ];
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }

    private function findSessionByRefreshToken(string $refreshToken): UserSession
    {
        $session = UserSession::query()
            ->with('user')
            ->where('refresh_token_hash', hash('sha256', $refreshToken))
            ->first();

        if (! $session || ! $session->user) {
            throw ValidationException::withMessages([
                'refresh_token' => 'The refresh token is invalid.',
            ]);
        }

        return $session;
    }

    private function revokeSession(UserSession $session, User $user, ?string $requestIp, ?string $userAgent): void
    {
        if ($session->revoked_at !== null) {
            return;
        }

        $session->forceFill([
            'refresh_token_hash' => null,
            'revoked_at' => now(),
        ])->save();

        $this->auditLogger->record(
            event: 'auth.logout',
            actor: $user,
            sessionUuid: $session->session_uuid,
            subjectType: 'session',
            subjectId: $session->session_uuid,
            context: [
                'message' => 'User logged out.',
            ],
            requestIp: $requestIp,
            userAgent: $userAgent,
        );
    }
}
