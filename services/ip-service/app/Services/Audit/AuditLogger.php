<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Support\ActorContext;

class AuditLogger
{
    public function record(
        string $event,
        ActorContext $actor,
        string $subjectType,
        string $subjectId,
        ?array $changes = null,
        array $context = [],
    ): void {
        AuditLog::query()->create([
            'category' => 'ip-management',
            'event' => $event,
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'actor_role' => $actor->role,
            'session_uuid' => $actor->sessionId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => $changes,
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }
}
