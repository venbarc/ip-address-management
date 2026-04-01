<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_request_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('correlation_id')->index();
            $table->string('method', 10);
            $table->string('path');
            $table->string('upstream');
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('actor_role')->nullable();
            $table->unsignedSmallInteger('response_status');
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_request_logs');
    }
};
