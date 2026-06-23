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
        $categories = DB::table('categories')->get()->map(static fn (object $row): array => [
            'old_id' => $row->id,
            'uuid' => (string) Str::uuid(),
            'parent_id' => $row->parent_id,
            'name' => $row->name,
            'is_active' => $row->is_active,
            'created_by' => $row->created_by ?? null,
            'updated_by' => $row->updated_by ?? null,
            'created_at' => $row->created_at ?? now(),
            'updated_at' => $row->updated_at ?? now(),
        ])->all();

        $pivot = Schema::hasTable('category_scenario')
            ? DB::table('category_scenario')->get()->map(static fn (object $row): array => [
                'category_id' => $row->category_id,
                'scenario_id' => $row->scenario_id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ])->all()
            : [];

        Schema::dropIfExists('category_scenario');

        $this->dropCategoriesParentForeignKey();

        Schema::dropIfExists('categories');

        Schema::create('categories', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();

            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('category_scenario', static function (Blueprint $table): void {
            $table->uuid('category_id');
            $table->uuid('scenario_id');

            $table->timestamps();

            $table->primary(['category_id', 'scenario_id']);

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();

            $table->foreign('scenario_id')
                ->references('id')
                ->on('scenarios')
                ->cascadeOnDelete();
        });

        $idMap = [];

        foreach ($categories as $category) {
            $idMap[(string) $category['old_id']] = $category['uuid'];
        }

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'id' => $category['uuid'],
                'parent_id' => $category['parent_id'] !== null
                    ? ($idMap[(string) $category['parent_id']] ?? null)
                    : null,
                'name' => $category['name'],
                'is_active' => $category['is_active'],
                'created_by' => $category['created_by'],
                'updated_by' => $category['updated_by'],
                'created_at' => $category['created_at'],
                'updated_at' => $category['updated_at'],
            ]);
        }

        Schema::table('categories', static function (Blueprint $table): void {
            $table->foreign('parent_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        });

        foreach ($pivot as $row) {
            if (! isset($idMap[(string) $row['category_id']])) {
                continue;
            }

            DB::table('category_scenario')->insert([
                'category_id' => $idMap[(string) $row['category_id']],
                'scenario_id' => $row['scenario_id'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);
        }
    }

    public function down(): void
    {
        $categories = DB::table('categories')->get()->map(static fn (object $row): array => [
            'old_id' => $row->id,
            'old_parent_id' => $row->parent_id ?? null,
            'name' => $row->name,
            'is_active' => $row->is_active,
            'created_by' => $row->created_by ?? null,
            'updated_by' => $row->updated_by ?? null,
            'created_at' => $row->created_at ?? now(),
            'updated_at' => $row->updated_at ?? now(),
        ])->all();

        $pivot = Schema::hasTable('category_scenario')
            ? DB::table('category_scenario')->get()->map(static fn (object $row): array => [
                'category_id' => $row->category_id,
                'scenario_id' => $row->scenario_id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ])->all()
            : [];

        Schema::dropIfExists('category_scenario');

        $this->dropCategoriesParentForeignKey();

        Schema::dropIfExists('categories');

        Schema::create('categories', static function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('category_scenario', static function (Blueprint $table): void {
            $table->unsignedBigInteger('category_id');
            $table->uuid('scenario_id');

            $table->timestamps();

            $table->primary(['category_id', 'scenario_id']);

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();

            $table->foreign('scenario_id')
                ->references('id')
                ->on('scenarios')
                ->cascadeOnDelete();
        });

        $idMap = [];

        foreach ($categories as $index => $category) {
            $id = $index + 1;

            $idMap[(string) $category['old_id']] = $id;

            DB::table('categories')->insert([
                'id' => $id,
                'parent_id' => null,
                'name' => $category['name'],
                'is_active' => $category['is_active'],
                'created_by' => $category['created_by'],
                'updated_by' => $category['updated_by'],
                'created_at' => $category['created_at'],
                'updated_at' => $category['updated_at'],
            ]);
        }

        foreach ($categories as $category) {
            if ($category['old_parent_id'] === null) {
                continue;
            }

            DB::table('categories')
                ->where('id', $idMap[(string) $category['old_id']] ?? 0)
                ->update([
                    'parent_id' => $idMap[(string) $category['old_parent_id']] ?? null,
                ]);
        }

        Schema::table('categories', static function (Blueprint $table): void {
            $table->foreign('parent_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        });

        foreach ($pivot as $row) {
            if (! isset($idMap[(string) $row['category_id']])) {
                continue;
            }

            DB::table('category_scenario')->insert([
                'category_id' => $idMap[(string) $row['category_id']],
                'scenario_id' => $row['scenario_id'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);
        }
    }

    private function dropCategoriesParentForeignKey(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'parent_id')) {
            return;
        }

        try {
            Schema::table('categories', static function (Blueprint $table): void {
                $table->dropForeign(['parent_id']);
            });
        } catch (Throwable) {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                DB::statement('ALTER TABLE categories DROP CONSTRAINT IF EXISTS categories_parent_id_foreign');
            }
        }
    }
};
