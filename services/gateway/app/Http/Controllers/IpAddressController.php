<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RelaysUpstreamResponses;
use App\Http\Requests\IpAddress\StoreIpAddressRequest;
use App\Http\Requests\IpAddress\UpdateIpAddressRequest;
use App\Services\Gateway\GatewayRequestLogger;
use App\Services\Gateway\IpServiceClient;
use App\Support\ActorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IpAddressController extends Controller
{
    use RelaysUpstreamResponses;

    public function __construct(
        private readonly IpServiceClient $ipServiceClient,
        private readonly GatewayRequestLogger $requestLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var ActorContext $actor */
        $actor = $request->attributes->get('actor');

        return $this->relay(
            request: $request,
            logger: $this->requestLogger,
            upstream: 'ip-service',
            callback: fn () => $this->ipServiceClient->list($actor),
            actor: $actor,
        );
    }

    public function store(StoreIpAddressRequest $request): JsonResponse
    {
        /** @var ActorContext $actor */
        $actor = $request->attributes->get('actor');

        return $this->relay(
            request: $request,
            logger: $this->requestLogger,
            upstream: 'ip-service',
            callback: fn () => $this->ipServiceClient->create($actor, $request->validated()),
            actor: $actor,
        );
    }

    public function update(UpdateIpAddressRequest $request, string $id): JsonResponse
    {
        /** @var ActorContext $actor */
        $actor = $request->attributes->get('actor');

        return $this->relay(
            request: $request,
            logger: $this->requestLogger,
            upstream: 'ip-service',
            callback: fn () => $this->ipServiceClient->update($actor, $id, $request->validated()),
            actor: $actor,
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var ActorContext $actor */
        $actor = $request->attributes->get('actor');

        return $this->relay(
            request: $request,
            logger: $this->requestLogger,
            upstream: 'ip-service',
            callback: fn () => $this->ipServiceClient->delete($actor, $id),
            actor: $actor,
        );
    }
}
