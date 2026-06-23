<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Module\Scenario\Models\Scenario;

return new class extends Migration
{
    public function up(): void
    {
        $rows = Schema::hasTable('category_scenario')
            ? DB::table('category_scenario')->get()->map(static fn (object $row): array => [
                'category_id' => $row->category_id,
                'model_id' => $row->scenario_id,
                'model_type' => Scenario::class,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ])->all()
            : [];

        Schema::create('model_has_categories', static function (Blueprint $table): void {
            $table->uuid('category_id');
            $table->uuid('model_id');
            $table->string('model_type');
            $table->timestamps();

            $table->primary(['category_id', 'model_id', 'model_type']);
            $table->index(['model_type', 'model_id']);

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();
        });

        foreach ($rows as $row) {
            DB::table('model_has_categories')->insert($row);
        }

        Schema::dropIfExists('category_scenario');
    }

    public function down(): void
    {
        $rows = Schema::hasTable('model_has_categories')
            ? DB::table('model_has_categories')
                ->where('model_type', Scenario::class)
                ->get()
                ->map(static fn (object $row): array => [
                    'category_id' => $row->category_id,
                    'scenario_id' => $row->model_id,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ])->all()
            : [];

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

        foreach ($rows as $row) {
            DB::table('category_scenario')->insert($row);
        }

        Schema::dropIfExists('model_has_categories');
    }
};
