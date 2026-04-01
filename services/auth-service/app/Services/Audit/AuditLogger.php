<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    public function record(
        string $event,
        ?User $actor,
        ?string $sessionUuid,
        string $subjectType,
        ?string $subjectId,
        ?array $changes = null,
        array $context = [],
        ?string $requestIp = null,
        ?string $userAgent = null,
    ): void {
        AuditLog::query()->create([
            'category' => 'auth',
            'event' => $event,
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'actor_role' => $actor?->role?->value,
            'session_uuid' => $sessionUuid,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => $changes,
            'context' => $context,
            'request_ip' => $requestIp,
            'user_agent' => $userAgent,
            'occurred_at' => now(),
        ]);
    }
}
