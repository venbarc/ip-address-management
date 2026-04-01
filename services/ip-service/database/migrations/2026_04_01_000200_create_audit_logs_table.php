<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('category');
            $table->string('event');
            $table->unsignedBigInteger('actor_user_id');
            $table->string('actor_name');
            $table->string('actor_email')->nullable();
            $table->string('actor_role');
            $table->string('session_uuid')->index();
            $table->string('subject_type');
            $table->string('subject_id');
            $table->json('changes')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
