<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password123',
            'role'     => UserRole::User,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user'                    => ['id', 'name', 'email', 'role'],
                    'access_token',
                    'token_type',
                    'expires_at',
                    'refresh_token',
                    'refresh_token_expires_at',
                ],
            ]);

        $this->assertEquals('Bearer', $response->json('data.token_type'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_creates_audit_log_entry(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login']);
    }

    // -------------------------------------------------------------------------
    // Token Refresh
    // -------------------------------------------------------------------------

    public function test_user_can_refresh_access_token(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $refreshToken = $loginResponse->json('data.refresh_token');

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'expires_at'],
            ]);
    }

    public function test_refresh_rotates_the_refresh_token(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $originalRefreshToken = $loginResponse->json('data.refresh_token');

        $refreshResponse = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $originalRefreshToken,
        ]);

        $newRefreshToken = $refreshResponse->json('data.refresh_token');

        $this->assertNotEquals($originalRefreshToken, $newRefreshToken);
    }

    public function test_refresh_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'this-is-not-a-valid-refresh-token',
        ]);

        $response->assertStatus(422);
    }

    public function test_used_refresh_token_cannot_be_reused(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $originalToken = $loginResponse->json('data.refresh_token');

        // Use it once — should succeed
        $this->postJson('/api/auth/refresh', ['refresh_token' => $originalToken])
            ->assertStatus(200);

        // Use it again — should fail (token was rotated)
        $this->postJson('/api/auth/refresh', ['refresh_token' => $originalToken])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    public function test_user_can_logout(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $accessToken = $loginResponse->json('data.access_token');

        $response = $this->withToken($accessToken)
            ->postJson('/api/auth/logout');

        $response->assertStatus(204);
    }

    public function test_logout_creates_audit_log_entry(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->withToken($loginResponse->json('data.access_token'))
            ->postJson('/api/auth/logout');

        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout']);
    }

    public function test_protected_route_requires_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Me endpoint
    // -------------------------------------------------------------------------

    public function test_me_returns_authenticated_user(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => 'password123',
            'role'     => UserRole::SuperAdmin,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $response = $this->withToken($loginResponse->json('data.access_token'))
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', 'user@example.com')
            ->assertJsonPath('data.user.role', 'super-admin');
    }
}
