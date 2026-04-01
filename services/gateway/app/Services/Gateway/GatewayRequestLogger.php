<?php

namespace App\Services\Gateway;

use App\Models\GatewayRequestLog;
use App\Support\ActorContext;
use Illuminate\Http\Request;

class GatewayRequestLogger
{
    public function record(Request $request, string $upstream, int $status, ?ActorContext $actor = null): void
    {
        GatewayRequestLog::query()->create([
            'correlation_id' => (string) $request->attributes->get('correlation_id', ''),
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'upstream' => $upstream,
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'response_status' => $status,
            'request_ip' => $request->ip(),
        ]);
    }
}
