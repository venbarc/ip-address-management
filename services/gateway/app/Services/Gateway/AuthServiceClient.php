<?php

namespace App\Services\Gateway;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AuthServiceClient
{
    public function login(array $payload): Response
    {
        return $this->publicClient()
            ->post('/api/auth/login', $payload);
    }

    public function refresh(array $payload): Response
    {
        return $this->publicClient()
            ->post('/api/auth/refresh', $payload);
    }

    public function logout(string $token): Response
    {
        return $this->publicClient()
            ->withToken($token)
            ->post('/api/auth/logout');
    }

    public function inspectAccessToken(string $token): array
    {
        return $this->internalClient()
            ->withToken($token)
            ->get('/api/internal/tokens/inspect')
            ->throw()
            ->json('data');
    }

    public function auditEvents(array $filters): array
    {
        return $this->internalClient()
            ->get('/api/internal/audit/events', $filters)
            ->throw()
            ->json('data', []);
    }

    private function publicClient(): PendingRequest
    {
        return Http::acceptJson()
            ->baseUrl(config('microservices.auth.base_url'))
            ->timeout(config('microservices.timeout_seconds'));
    }

    private function internalClient(): PendingRequest
    {
        return $this->publicClient()->withHeaders([
            'X-Internal-Secret' => config('microservices.internal_service_secret'),
        ]);
    }
}
