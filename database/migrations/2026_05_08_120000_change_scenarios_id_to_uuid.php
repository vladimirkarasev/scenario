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
        // Save existing scenario data with new UUIDs before dropping anything
        $scenarios = DB::table('scenarios')->get()->map(static function (object $row): array {
            return [
                'uuid' => (string) Str::uuid(),
                'name' => $row->name,
                'description' => $row->description ?? null,
                'is_active' => $row->is_active,
                'tag' => $row->tag ?? null,
                'aliases' => $row->aliases ?? null,
                'created_by' => $row->created_by ?? null,
                'updated_by' => $row->updated_by ?? null,
                'created_at' => $row->created_at ?? null,
                'updated_at' => $row->updated_at ?? null,
            ];
        })->all();

        // Drop all dependent tables (versions cascade to revisions and runs)
        Schema::dropIfExists('category_scenario');
        Schema::dropIfExists('scenario_run_steps');
        Schema::dropIfExists('scenario_runs');
        Schema::dropIfExists('scenario_version_revisions');
        $this->dropScenariosActiveVersionForeignKey();
        Schema::dropIfExists('scenario_versions');
        Schema::dropIfExists('scenarios');

        // Recreate scenarios with UUID primary key
        Schema::create('scenarios', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('tag')->nullable();
            $table->json('aliases')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('active_version_id')->nullable();
            $table->timestamps();
        });

        // Recreate scenario_versions with UUID scenario_id
        Schema::create('scenario_versions', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('scenario_id');
            $table->string('name')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('scenario_id')->references('id')->on('scenarios')->cascadeOnDelete();
        });

        // Add active_version FK now that scenario_versions exists
        Schema::table('scenarios', static function (Blueprint $table): void {
            $table->foreign('active_version_id')
                ->references('id')
                ->on('scenario_versions')
                ->nullOnDelete();
        });

        // Recreate scenario_version_revisions
        Schema::create('scenario_version_revisions', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('scenario_version_id');
            $table->json('schema_json')->nullable();
            $table->json('nodes_json')->nullable();
            $table->json('edges_json')->nullable();
            $table->integer('schema_version')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('scenario_version_id')
                ->references('id')
                ->on('scenario_versions')
                ->cascadeOnDelete();
        });

        // Recreate scenario_runs with UUID scenario_id
        Schema::create('scenario_runs', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('scenario_id');
            $table->uuid('scenario_version_id');
            $table->uuid('scenario_version_revision_id');
            $table->string('current_node_id')->nullable();
            $table->json('context')->nullable();
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('scenario_id')->references('id')->on('scenarios')->cascadeOnDelete();
            $table->foreign('scenario_version_id')->references('id')->on('scenario_versions')->cascadeOnDelete();
            $table->foreign('scenario_version_revision_id')->references('id')->on('scenario_version_revisions')->cascadeOnDelete();

            $table->index(['scenario_id', 'status']);
            $table->index(['scenario_version_id']);
        });

        // Recreate scenario_run_steps
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

            $table->foreign('run_id')->references('id')->on('scenario_runs')->cascadeOnDelete();
            $table->index(['run_id', 'node_id']);
        });

        // Recreate category_scenario pivot with UUID scenario_id
        Schema::create('category_scenario', static function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->uuid('scenario_id');
            $table->timestamps();

            $table->primary(['category_id', 'scenario_id']);
            $table->foreign('scenario_id')->references('id')->on('scenarios')->cascadeOnDelete();
        });

        // Reinsert scenarios with new UUIDs
        foreach ($scenarios as $scenario) {
            DB::table('scenarios')->insert([
                'id' => $scenario['uuid'],
                'name' => $scenario['name'],
                'description' => $scenario['description'],
                'is_active' => $scenario['is_active'],
                'tag' => $scenario['tag'],
                'aliases' => $scenario['aliases'],
                'created_by' => $scenario['created_by'],
                'updated_by' => $scenario['updated_by'],
                'created_at' => $scenario['created_at'],
                'updated_at' => $scenario['updated_at'],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_scenario');
        Schema::dropIfExists('scenario_run_steps');
        Schema::dropIfExists('scenario_runs');
        Schema::dropIfExists('scenario_version_revisions');
        $this->dropScenariosActiveVersionForeignKey();
        Schema::dropIfExists('scenario_versions');
        Schema::dropIfExists('scenarios');

        Schema::create('scenarios', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('tag')->nullable();
            $table->json('aliases')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    private function dropScenariosActiveVersionForeignKey(): void
    {
        if (! Schema::hasTable('scenarios') || ! Schema::hasColumn('scenarios', 'active_version_id')) {
            return;
        }

        Schema::table('scenarios', static function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
        });
    }
};
