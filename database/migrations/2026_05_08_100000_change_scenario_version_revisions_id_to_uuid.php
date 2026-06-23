<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('scenario_version_revisions')
            ->orderBy('created_at')
            ->get();

        Schema::dropIfExists('scenario_version_revisions');

        Schema::create('scenario_version_revisions', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
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

        foreach ($existing as $row) {
            DB::table('scenario_version_revisions')->insert([
                'id' => (string) Str::uuid(),
                'scenario_version_id' => $row->scenario_version_id,
                'schema_json' => $row->schema_json,
                'nodes_json' => $row->nodes_json,
                'edges_json' => $row->edges_json,
                'schema_version' => $row->schema_version,
                'created_at' => $row->created_at,
            ]);
        }
    }

    public function down(): void
    {
        $existing = DB::table('scenario_version_revisions')
            ->orderBy('created_at')
            ->get();

        Schema::dropIfExists('scenario_version_revisions');

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

        foreach ($existing as $row) {
            DB::table('scenario_version_revisions')->insert([
                'scenario_version_id' => $row->scenario_version_id,
                'schema_json' => $row->schema_json,
                'nodes_json' => $row->nodes_json,
                'edges_json' => $row->edges_json,
                'schema_version' => $row->schema_version,
                'created_at' => $row->created_at,
            ]);
        }
    }
};
