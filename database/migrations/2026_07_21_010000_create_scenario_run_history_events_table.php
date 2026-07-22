<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_run_history_events', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_id');
            $table->foreignId('step_id')->nullable();
            $table->string('scenario_version_id')->nullable();
            $table->string('scenario_version_revision_id')->nullable();
            $table->string('node_id')->nullable();
            $table->string('node_type')->nullable();
            $table->string('type');
            $table->foreignId('actor_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('run_id')
                ->references('id')
                ->on('scenario_runs')
                ->cascadeOnDelete();

            $table->foreign('step_id')
                ->references('id')
                ->on('scenario_run_steps')
                ->nullOnDelete();

            $table->foreign('actor_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['run_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_run_history_events');
    }
};
