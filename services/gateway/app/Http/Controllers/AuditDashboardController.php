<?php

namespace App\Http\Controllers;

use App\Services\Gateway\AuthServiceClient;
use App\Services\Gateway\GatewayRequestLogger;
use App\Services\Gateway\IpServiceClient;
use App\Support\ActorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

class AuditDashboardController extends Controller
{
    public function __construct(
        private readonly AuthServiceClient $authServiceClient,
        private readonly IpServiceClient $ipServiceClient,
        private readonly GatewayRequestLogger $requestLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var ActorContext $actor */
        $actor = $request->attributes->get('actor');

        $filters = array_filter([
            'limit' => min(max($request->integer('limit', 150), 1), 250),
            'actor_user_id' => $request->query('actor_user_id'),
            'session_uuid' => $request->query('session_uuid'),
            'subject_id' => $request->query('subject_id'),
            'event' => $request->query('event'),
        ], static fn ($value) => $value !== null && $value !== '');

        try {
            $authEvents = collect($this->authServiceClient->auditEvents($filters));
            $ipEvents = collect($this->ipServiceClient->auditEvents($actor, $filters));
            $events = $authEvents
                ->concat($ipEvents)
                ->sortByDesc('occurred_at')
                ->values()
                ->take((int) ($filters['limit'] ?? 150))
                ->all();

            $response = [
                'data' => [
                    'summary' => [
                        'total_events' => count($events),
                        'auth_events' => $authEvents->count(),
                        'ip_events' => $ipEvents->count(),
                        'sessions_seen' => collect($events)->pluck('session_uuid')->filter()->unique()->count(),
                    ],
                    'events' => $events,
                ],
            ];

            $status = 200;
        } catch (Throwable) {
            $response = [
                'message' => 'Unable to assemble the audit dashboard.',
            ];
            $status = 502;
        }

        $this->requestLogger->record($request, 'auth-service+ip-service', $status, $actor);

        return response()->json($response, $status);
    }
}
