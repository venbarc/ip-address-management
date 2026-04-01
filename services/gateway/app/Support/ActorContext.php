<?php

namespace App\Support;

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

    public static function fromInspectPayload(array $payload): self
    {
        return new self(
            id: (int) data_get($payload, 'user.id'),
            name: (string) data_get($payload, 'user.name', 'Unknown User'),
            email: (string) data_get($payload, 'user.email', ''),
            role: (string) data_get($payload, 'user.role', 'user'),
            sessionId: (string) data_get($payload, 'session.id'),
        );
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super-admin';
    }

    public function toHeaders(): array
    {
        return [
            'X-Actor-Id' => (string) $this->id,
            'X-Actor-Name' => $this->name,
            'X-Actor-Email' => $this->email,
            'X-Actor-Role' => $this->role,
            'X-Session-Id' => $this->sessionId,
        ];
    }
}
