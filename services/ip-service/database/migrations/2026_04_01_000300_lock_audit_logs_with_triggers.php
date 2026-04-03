<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::unprepared("
                CREATE TRIGGER prevent_audit_log_update
                BEFORE UPDATE ON audit_logs
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Audit logs are immutable: updates are not permitted.';
            ");

            DB::unprepared("
                CREATE TRIGGER prevent_audit_log_delete
                BEFORE DELETE ON audit_logs
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Audit logs are immutable: deletions are not permitted.';
            ");
        } catch (\Throwable $e) {
            // Requires log_bin_trust_function_creators=1 or SUPER privilege.
            // Model-level guard still enforces immutability if triggers cannot be created.
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS prevent_audit_log_update');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_audit_log_delete');
    }
};
