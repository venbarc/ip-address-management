<?php

namespace App\Services\Gateway;

use App\Support\ActorContext;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class IpServiceClient
{
    public function list(ActorContext $actor): Response
    {
        return $this->client($actor)
            ->get('/api/internal/ip-addresses');
    }

    public function create(ActorContext $actor, array $payload): Response
    {
        return $this->client($actor)
            ->post('/api/internal/ip-addresses', $payload);
    }

    public function update(ActorContext $actor, string $id, array $payload): Response
    {
        return $this->client($actor)
            ->patch('/api/internal/ip-addresses/'.$id, $payload);
    }

    public function delete(ActorContext $actor, string $id): Response
    {
        return $this->client($actor)
            ->delete('/api/internal/ip-addresses/'.$id);
    }

    public function auditEvents(ActorContext $actor, array $filters): array
    {
        return $this->client($actor)
            ->get('/api/internal/audit/events', $filters)
            ->throw()
            ->json('data', []);
    }

    private function client(ActorContext $actor): PendingRequest
    {
        return Http::acceptJson()
            ->baseUrl(config('microservices.ip.base_url'))
            ->timeout(config('microservices.timeout_seconds'))
            ->withHeaders([
                'X-Internal-Secret' => config('microservices.internal_service_secret'),
                ...$actor->toHeaders(),
            ]);
    }
}
