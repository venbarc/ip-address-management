<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeAuditLog(): AuditLog
    {
        return AuditLog::create([
            'category'      => 'auth',
            'event'         => 'auth.login',
            'actor_user_id' => null,
            'actor_name'    => 'Test User',
            'actor_email'   => 'test@example.com',
            'actor_role'    => 'user',
            'session_uuid'  => 'test-session-uuid',
            'subject_type'  => 'session',
            'subject_id'    => 'test-session-uuid',
            'occurred_at'   => now(),
        ]);
    }

    public function test_audit_log_can_be_created(): void
    {
        $log = $this->makeAuditLog();

        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login']);
        $this->assertNotNull($log->id);
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $log = $this->makeAuditLog();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Audit logs are immutable.');

        $log->update(['event' => 'auth.tampered']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $log = $this->makeAuditLog();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Audit logs are immutable.');

        $log->delete();
    }

    public function test_audit_log_cannot_be_force_deleted_via_model(): void
    {
        $log = $this->makeAuditLog();

        // forceDelete also goes through the deleting event
        $this->expectException(LogicException::class);

        $log->forceDelete();
    }

    public function test_audit_log_stores_context_as_json(): void
    {
        $log = AuditLog::create([
            'category'      => 'auth',
            'event'         => 'auth.login',
            'actor_user_id' => null,
            'actor_name'    => 'Test User',
            'actor_email'   => 'test@example.com',
            'actor_role'    => 'user',
            'session_uuid'  => 'uuid-1',
            'subject_type'  => 'session',
            'subject_id'    => 'uuid-1',
            'context'       => ['message' => 'User logged in.'],
            'occurred_at'   => now(),
        ]);

        $this->assertEquals('User logged in.', $log->context['message']);
    }
}
