<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('directory_import_schedules');
    }

    public function down(): void
    {
        Schema::create('directory_import_schedules', static function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('directory_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('frequency')->default('daily');
            $table->time('run_at')->nullable();
            $table->string('timezone')->default(config('app.timezone', 'UTC'));
            $table->string('mode');
            $table->string('match_by')->nullable();
            $table->unsignedInteger('chunk_size')->default(500);
            $table->json('mapping_json');
            $table->json('fields_json');
            $table->json('remote_config_json');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->unique('directory_id');
            $table->index(['enabled', 'next_run_at']);
        });
    }
};
