<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayProxyTest extends TestCase
{
    private array $userInspectPayload = [
        'user'    => ['id' => 1, 'name' => 'Test User', 'email' => 'user@example.com', 'role' => 'user'],
        'session' => ['id' => 'test-session-uuid'],
    ];

    private array $superAdminInspectPayload = [
        'user'    => ['id' => 2, 'name' => 'Admin User', 'email' => 'admin@example.com', 'role' => 'super-admin'],
        'session' => ['id' => 'admin-session-uuid'],
    ];

    // -------------------------------------------------------------------------
    // Auth enforcement
    // -------------------------------------------------------------------------

    public function test_protected_ip_route_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/ip-addresses');

        $response->assertStatus(401);
    }

    public function test_protected_audit_route_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/audit/dashboard');

        $response->assertStatus(401);
    }

    public function test_protected_me_route_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Login proxy
    // -------------------------------------------------------------------------

    public function test_login_is_proxied_to_auth_service_and_returns_token(): void
    {
        $authBundle = [
            'user'                    => ['id' => 1, 'name' => 'Test User', 'email' => 'user@example.com', 'role' => 'user'],
            'access_token'            => 'eyJ.test.jwt',
            'refresh_token'           => 'refresh-abc',
            'token_type'              => 'Bearer',
            'expires_at'              => now()->addMinutes(15)->toISOString(),
            'expires_in'              => 900,
            'session_id'              => 'session-uuid',
            'refresh_token_expires_at' => now()->addDays(7)->toISOString(),
        ];

        Http::fake([
            '*' => Http::response(['data' => $authBundle], 200),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.access_token', 'eyJ.test.jwt');
    }

    // -------------------------------------------------------------------------
    // IP address proxy
    // -------------------------------------------------------------------------

    public function test_ip_list_is_proxied_with_valid_token(): void
    {
        Http::fake([
            '*/inspect' => Http::response(['data' => $this->userInspectPayload], 200),
            '*'         => Http::response(['data' => []], 200),
        ]);

        $response = $this->withToken('valid-token')->getJson('/api/ip-addresses');

        $response->assertStatus(200);
    }

    public function test_ip_store_is_proxied_with_valid_token(): void
    {
        $record = [
            'id'      => '01jqz000000000000000000001',
            'address' => '10.0.0.1',
            'version' => 4,
            'label'   => 'Test record',
            'comment' => null,
        ];

        Http::fake([
            '*/inspect' => Http::response(['data' => $this->userInspectPayload], 200),
            '*'         => Http::response(['data' => $record], 201),
        ]);

        $response = $this->withToken('valid-token')->postJson('/api/ip-addresses', [
            'address' => '10.0.0.1',
            'label'   => 'Test record',
            'comment' => '',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.address', '10.0.0.1');
    }

    // -------------------------------------------------------------------------
    // Role-based access
    // -------------------------------------------------------------------------

    public function test_regular_user_cannot_access_audit_dashboard(): void
    {
        Http::fake([
            '*/inspect' => Http::response(['data' => $this->userInspectPayload], 200),
            '*'         => Http::response(['data' => []], 200),
        ]);

        $response = $this->withToken('valid-token')->getJson('/api/audit/dashboard');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_audit_dashboard(): void
    {
        Http::fake([
            '*/inspect'   => Http::response(['data' => $this->superAdminInspectPayload], 200),
            '*/events'    => Http::response(['data' => []], 200),
            '*'           => Http::response(['data' => []], 200),
        ]);

        $response = $this->withToken('valid-token')->getJson('/api/audit/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['summary', 'events']]);
    }
}
