<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', static function (Blueprint $table): void {
            $table->id();
            $table->string('scope');
            $table->string('subject_id');
            $table->boolean('enabled')->default(true);
            $table->string('cron')->nullable();
            $table->string('timezone')->default(config('app.timezone', 'UTC'));
            $table->string('workflow_type');
            $table->string('task_queue')->default('default');
            $table->json('workflow_input')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'subject_id']);
            $table->index(['enabled', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
