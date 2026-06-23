<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->uuid('request_uuid');
            $table->string('request_id')->unique();
            $table->string('parent_request_id')->nullable()->index();
            $table->unsignedInteger('attempt')->default(1);
            $table->unsignedInteger('max_attempts')->default(1);
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->string('status', 32)->default('received')->index();
            $table->string('method', 16);
            $table->text('path');
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('headers_masked')->nullable();
            $table->json('query_params')->nullable();
            $table->json('payload')->nullable();
            $table->json('normalized_data')->nullable();
            $table->json('message_box')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('webhook_endpoint_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_requests');
    }
};
