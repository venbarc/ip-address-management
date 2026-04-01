<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Gateway\GatewayRequestLogger;
use App\Support\ActorContext;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

trait RelaysUpstreamResponses
{
    protected function relay(
        Request $request,
        GatewayRequestLogger $logger,
        string $upstream,
        callable $callback,
        ?ActorContext $actor = null,
    ): JsonResponse {
        try {
            /** @var Response $response */
            $response = $callback();
            $payload = $response->status() === 204 ? [] : ($response->json() ?? []);
            $status = $response->status();
        } catch (Throwable $exception) {
            $response = $exception instanceof RequestException ? $exception->response : null;
            $payload = $response?->json() ?? ['message' => 'Upstream request failed.'];
            $status = $response?->status() ?? 502;
        }

        $logger->record($request, $upstream, $status, $actor);

        return response()->json($payload, $status);
    }
}
