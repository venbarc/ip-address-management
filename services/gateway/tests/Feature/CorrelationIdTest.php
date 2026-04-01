<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CorrelationIdTest extends TestCase
{
    public function test_response_includes_correlation_id_header(): void
    {
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertTrue(
            $response->headers->has('X-Correlation-Id'),
            'Response should include X-Correlation-Id header'
        );
    }

    public function test_provided_correlation_id_is_echoed_back(): void
    {
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $correlationId = 'my-trace-id-12345';

        $response = $this->withHeaders(['X-Correlation-Id' => $correlationId])
            ->postJson('/api/auth/login', [
                'email'    => 'user@example.com',
                'password' => 'password123',
            ]);

        $this->assertEquals(
            $correlationId,
            $response->headers->get('X-Correlation-Id')
        );
    }

    public function test_correlation_id_is_generated_when_not_provided(): void
    {
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $correlationId = $response->headers->get('X-Correlation-Id');

        $this->assertNotNull($correlationId);
        $this->assertNotEmpty($correlationId);
    }
}
