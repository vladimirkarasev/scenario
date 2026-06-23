<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_run_steps', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_id');
            $table->string('node_id');
            $table->string('node_type');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->timestamp('entered_at')->nullable();
            $table->timestamp('exited_at')->nullable();
            $table->timestamps();

            $table->foreign('run_id')
                ->references('id')
                ->on('scenario_runs')
                ->cascadeOnDelete();

            $table->index(['run_id', 'node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_run_steps');
    }
};
