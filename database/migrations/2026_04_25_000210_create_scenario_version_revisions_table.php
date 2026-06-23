<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_version_revisions', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('scenario_version_id');
            $table->json('schema_json');
            $table->json('nodes_json')->nullable();
            $table->json('edges_json')->nullable();
            $table->unsignedInteger('schema_version')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('scenario_version_id')
                ->references('id')
                ->on('scenario_versions')
                ->cascadeOnDelete();

            $table->index(['scenario_version_id', 'created_at']);
        });

        DB::table('scenario_versions')
            ->orderBy('created_at')
            ->get()
            ->each(static function (object $version): void {
                DB::table('scenario_version_revisions')->insert([
                    'scenario_version_id' => $version->id,
                    'schema_json' => $version->schema_json,
                    'nodes_json' => $version->nodes_json,
                    'edges_json' => $version->edges_json,
                    'schema_version' => $version->schema_version ?? 1,
                    'created_at' => $version->created_at ?? now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_version_revisions');
    }
};
