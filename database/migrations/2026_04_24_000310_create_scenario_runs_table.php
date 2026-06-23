<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_runs', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->uuid('scenario_version_id');
            $table->string('current_node_id')->nullable();
            $table->json('context')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->foreign('scenario_version_id')
                ->references('id')
                ->on('scenario_versions')
                ->cascadeOnDelete();

            $table->index(['scenario_id', 'status']);
            $table->index(['scenario_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_runs');
    }
};
