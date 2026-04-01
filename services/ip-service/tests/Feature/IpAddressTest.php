<?php

namespace Tests\Feature;

use App\Models\IpAddressRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpAddressTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-internal-secret';

    private const REGULAR_USER_HEADERS = [
        'X-Internal-Secret' => self::SECRET,
        'X-Actor-Id'        => '1',
        'X-Actor-Name'      => 'Regular User',
        'X-Actor-Email'     => 'user@example.com',
        'X-Actor-Role'      => 'user',
        'X-Session-Id'      => 'session-uuid-user',
    ];

    private const SUPER_ADMIN_HEADERS = [
        'X-Internal-Secret' => self::SECRET,
        'X-Actor-Id'        => '99',
        'X-Actor-Name'      => 'Super Admin',
        'X-Actor-Email'     => 'admin@example.com',
        'X-Actor-Role'      => 'super-admin',
        'X-Session-Id'      => 'session-uuid-admin',
    ];

    private const OTHER_USER_HEADERS = [
        'X-Internal-Secret' => self::SECRET,
        'X-Actor-Id'        => '2',
        'X-Actor-Name'      => 'Other User',
        'X-Actor-Email'     => 'other@example.com',
        'X-Actor-Role'      => 'user',
        'X-Session-Id'      => 'session-uuid-other',
    ];

    // -------------------------------------------------------------------------
    // Internal secret guard
    // -------------------------------------------------------------------------

    public function test_requests_without_internal_secret_are_rejected(): void
    {
        $this->getJson('/api/internal/ip-addresses')
            ->assertStatus(403);
    }

    public function test_requests_with_wrong_secret_are_rejected(): void
    {
        $this->withHeaders(['X-Internal-Secret' => 'wrong-secret'])
            ->getJson('/api/internal/ip-addresses')
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function test_any_authenticated_user_can_list_ip_addresses(): void
    {
        IpAddressRecord::create([
            'address'            => '192.168.1.1',
            'normalized_address' => bin2hex(inet_pton('192.168.1.1')),
            'version'            => 4,
            'label'              => 'Test Server',
            'created_by_user_id' => 1,
            'created_by_name'    => 'User',
            'created_by_email'   => 'user@example.com',
            'updated_by_user_id' => 1,
            'updated_by_name'    => 'User',
            'updated_by_email'   => 'user@example.com',
        ]);

        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->getJson('/api/internal/ip-addresses')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function test_user_can_create_ipv4_address(): void
    {
        $response = $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.0.0.1',
                'label'   => 'Dev Server',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.address', '10.0.0.1')
            ->assertJsonPath('data.version', 4)
            ->assertJsonPath('data.label', 'Dev Server');

        $this->assertDatabaseHas('ip_addresses', ['address' => '10.0.0.1']);
    }

    public function test_user_can_create_ipv6_address(): void
    {
        $response = $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '2001:db8::1',
                'label'   => 'IPv6 Server',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.version', 6);
    }

    public function test_create_with_optional_comment(): void
    {
        $response = $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '172.16.0.1',
                'label'   => 'Internal Gateway',
                'comment' => 'Primary internal gateway for VLAN 10',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.comment', 'Primary internal gateway for VLAN 10');
    }

    public function test_cannot_create_duplicate_ip_address(): void
    {
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.0.0.5',
                'label'   => 'First Entry',
            ]);

        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.0.0.5',
                'label'   => 'Duplicate Entry',
            ])
            ->assertStatus(422);
    }

    public function test_cannot_create_with_invalid_ip_address(): void
    {
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => 'not-an-ip',
                'label'   => 'Bad Address',
            ])
            ->assertStatus(422);
    }

    public function test_label_is_required(): void
    {
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.0.0.1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['label']);
    }

    public function test_create_logs_audit_event(): void
    {
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.10.10.10',
                'label'   => 'Audited Server',
            ]);

        $this->assertDatabaseHas('audit_logs', ['event' => 'ip.created']);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_user_can_update_own_ip_record(): void
    {
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.1.1.1',
                'label'   => 'Original Label',
            ]);

        $record = IpAddressRecord::first();

        $response = $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->patchJson("/api/internal/ip-addresses/{$record->id}", [
                'label' => 'Updated Label',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.label', 'Updated Label');
    }

    public function test_regular_user_cannot_update_another_users_record(): void
    {
        // Created by user 1
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.2.2.2',
                'label'   => 'User 1 Record',
            ]);

        $record = IpAddressRecord::first();

        // Attempted update by user 2
        $this->withHeaders(self::OTHER_USER_HEADERS)
            ->patchJson("/api/internal/ip-addresses/{$record->id}", [
                'label' => 'Hijacked Label',
            ])
            ->assertStatus(403);
    }

    public function test_super_admin_can_update_any_record(): void
    {
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.3.3.3',
                'label'   => 'User Record',
            ]);

        $record = IpAddressRecord::first();

        $this->withHeaders(self::SUPER_ADMIN_HEADERS)
            ->patchJson("/api/internal/ip-addresses/{$record->id}", [
                'label' => 'Admin Updated',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.label', 'Admin Updated');
    }

    public function test_update_logs_audit_event(): void
    {
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.4.4.4',
                'label'   => 'Before Update',
            ]);

        $record = IpAddressRecord::first();

        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->patchJson("/api/internal/ip-addresses/{$record->id}", [
                'label' => 'After Update',
            ]);

        $this->assertDatabaseHas('audit_logs', ['event' => 'ip.updated']);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function test_regular_user_cannot_delete_any_record(): void
    {
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.5.5.5',
                'label'   => 'My Record',
            ]);

        $record = IpAddressRecord::first();

        // Even the owner cannot delete
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->deleteJson("/api/internal/ip-addresses/{$record->id}")
            ->assertStatus(403);
    }

    public function test_super_admin_can_delete_any_record(): void
    {
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.6.6.6',
                'label'   => 'To Be Deleted',
            ]);

        $record = IpAddressRecord::first();

        $this->withHeaders(self::SUPER_ADMIN_HEADERS)
            ->deleteJson("/api/internal/ip-addresses/{$record->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('ip_addresses', ['id' => $record->id]);
    }

    public function test_delete_logs_audit_event(): void
    {
        $this->withHeaders(self::REGULAR_USER_HEADERS)
            ->postJson('/api/internal/ip-addresses', [
                'address' => '10.7.7.7',
                'label'   => 'Will Be Deleted',
            ]);

        $record = IpAddressRecord::first();

        $this->withHeaders(self::SUPER_ADMIN_HEADERS)
            ->deleteJson("/api/internal/ip-addresses/{$record->id}");

        $this->assertDatabaseHas('audit_logs', ['event' => 'ip.deleted']);
    }
}
