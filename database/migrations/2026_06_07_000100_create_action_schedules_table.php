<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_schedules', static function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('action_id')->constrained('actions')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('cron')->nullable();
            $table->string('timezone')->default(config('app.timezone', 'UTC'));
            $table->json('input')->nullable();
            $table->json('options')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->unique('action_id');
            $table->index(['enabled', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_schedules');
    }
};
