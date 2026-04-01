<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_addresses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('address', 45);
            $table->string('normalized_address')->unique();
            $table->unsignedTinyInteger('version');
            $table->string('label', 120);
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->string('created_by_name');
            $table->string('created_by_email');
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->string('updated_by_name')->nullable();
            $table->string('updated_by_email')->nullable();
            $table->timestamps();

            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_addresses');
    }
};
