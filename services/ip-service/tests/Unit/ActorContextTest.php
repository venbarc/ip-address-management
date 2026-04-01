<?php

namespace Tests\Unit;

use App\Support\ActorContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ActorContextTest extends TestCase
{
    private function makeRequest(array $headers = []): Request
    {
        $request = Request::create('/test', 'GET');

        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        return $request;
    }

    public function test_actor_context_is_built_from_headers(): void
    {
        $request = $this->makeRequest([
            'X-Actor-Id'    => '42',
            'X-Actor-Name'  => 'John Doe',
            'X-Actor-Email' => 'john@example.com',
            'X-Actor-Role'  => 'user',
            'X-Session-Id'  => 'session-abc',
        ]);

        $actor = ActorContext::fromRequest($request);

        $this->assertEquals(42, $actor->id);
        $this->assertEquals('John Doe', $actor->name);
        $this->assertEquals('john@example.com', $actor->email);
        $this->assertEquals('user', $actor->role);
        $this->assertEquals('session-abc', $actor->sessionId);
    }

    public function test_throws_when_actor_id_is_missing(): void
    {
        $this->expectException(AuthorizationException::class);

        $request = $this->makeRequest([
            'X-Session-Id' => 'session-abc',
        ]);

        ActorContext::fromRequest($request);
    }

    public function test_throws_when_session_id_is_missing(): void
    {
        $this->expectException(AuthorizationException::class);

        $request = $this->makeRequest([
            'X-Actor-Id' => '1',
        ]);

        ActorContext::fromRequest($request);
    }

    public function test_regular_user_is_not_super_admin(): void
    {
        $request = $this->makeRequest([
            'X-Actor-Id'   => '1',
            'X-Actor-Role' => 'user',
            'X-Session-Id' => 'session-1',
        ]);

        $actor = ActorContext::fromRequest($request);

        $this->assertFalse($actor->isSuperAdmin());
    }

    public function test_super_admin_role_is_detected(): void
    {
        $request = $this->makeRequest([
            'X-Actor-Id'   => '99',
            'X-Actor-Role' => 'super-admin',
            'X-Session-Id' => 'session-99',
        ]);

        $actor = ActorContext::fromRequest($request);

        $this->assertTrue($actor->isSuperAdmin());
    }

    public function test_user_can_modify_own_record(): void
    {
        $request = $this->makeRequest([
            'X-Actor-Id'   => '5',
            'X-Actor-Role' => 'user',
            'X-Session-Id' => 'session-5',
        ]);

        $actor = ActorContext::fromRequest($request);

        $this->assertTrue($actor->canModifyRecordOwnedBy(5));
    }

    public function test_user_cannot_modify_another_users_record(): void
    {
        $request = $this->makeRequest([
            'X-Actor-Id'   => '5',
            'X-Actor-Role' => 'user',
            'X-Session-Id' => 'session-5',
        ]);

        $actor = ActorContext::fromRequest($request);

        $this->assertFalse($actor->canModifyRecordOwnedBy(6));
    }

    public function test_super_admin_can_modify_any_record(): void
    {
        $request = $this->makeRequest([
            'X-Actor-Id'   => '99',
            'X-Actor-Role' => 'super-admin',
            'X-Session-Id' => 'session-99',
        ]);

        $actor = ActorContext::fromRequest($request);

        $this->assertTrue($actor->canModifyRecordOwnedBy(1));
        $this->assertTrue($actor->canModifyRecordOwnedBy(2));
        $this->assertTrue($actor->canModifyRecordOwnedBy(999));
    }
}
