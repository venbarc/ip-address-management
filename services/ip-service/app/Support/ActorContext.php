<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class ActorContext
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly string $sessionId,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $actorId = $request->header('X-Actor-Id');
        $sessionId = $request->header('X-Session-Id');

        if (! $actorId || ! $sessionId) {
            throw new AuthorizationException('The actor context is incomplete.');
        }

        return new self(
            id: (int) $actorId,
            name: (string) $request->header('X-Actor-Name', 'Unknown User'),
            email: (string) $request->header('X-Actor-Email', ''),
            role: (string) $request->header('X-Actor-Role', 'user'),
            sessionId: (string) $sessionId,
        );
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super-admin';
    }

    public function canModifyRecordOwnedBy(int $ownerUserId): bool
    {
        return $this->isSuperAdmin() || $this->id === $ownerUserId;
    }
}
