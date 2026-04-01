<?php

namespace Tests\Unit;

use App\Support\ActorContext;
use PHPUnit\Framework\TestCase;

class ActorContextTest extends TestCase
{
    private function makeActor(string $role = 'user'): ActorContext
    {
        return ActorContext::fromInspectPayload([
            'user' => [
                'id'    => 1,
                'name'  => 'Test User',
                'email' => 'test@example.com',
                'role'  => $role,
            ],
            'session' => [
                'id' => 'session-uuid-123',
            ],
        ]);
    }

    public function test_actor_is_built_from_inspect_payload(): void
    {
        $actor = $this->makeActor('user');

        $this->assertEquals(1, $actor->id);
        $this->assertEquals('Test User', $actor->name);
        $this->assertEquals('test@example.com', $actor->email);
        $this->assertEquals('user', $actor->role);
        $this->assertEquals('session-uuid-123', $actor->sessionId);
    }

    public function test_regular_user_is_not_super_admin(): void
    {
        $actor = $this->makeActor('user');

        $this->assertFalse($actor->isSuperAdmin());
    }

    public function test_super_admin_role_is_detected(): void
    {
        $actor = $this->makeActor('super-admin');

        $this->assertTrue($actor->isSuperAdmin());
    }

    public function test_to_headers_returns_all_actor_fields(): void
    {
        $actor = $this->makeActor('super-admin');
        $headers = $actor->toHeaders();

        $this->assertArrayHasKey('X-Actor-Id', $headers);
        $this->assertArrayHasKey('X-Actor-Name', $headers);
        $this->assertArrayHasKey('X-Actor-Email', $headers);
        $this->assertArrayHasKey('X-Actor-Role', $headers);
        $this->assertArrayHasKey('X-Session-Id', $headers);
        $this->assertEquals('super-admin', $headers['X-Actor-Role']);
    }
}
