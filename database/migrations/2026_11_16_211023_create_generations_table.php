<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->json('reference_images');
            $table->string('status')->default('pending');
            $table->string('pixelforge_generation_id')->nullable()->unique();
            $table->uuid('idempotency_key')->unique();

            $table->decimal('estimated_cost', 20, 11);
            $table->decimal('provider_cost', 20, 11)->nullable();
            $table->decimal('final_cost', 20, 11)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->json('rates')->nullable();
            $table->json('actual_usage')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'updated_at']);
            $table->index(['completed_at', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
