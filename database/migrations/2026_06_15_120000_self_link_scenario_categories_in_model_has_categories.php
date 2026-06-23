<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Module\Scenario\Models\Scenario;

return new class extends Migration
{
    public function up(): void
    {
        $modelType = Scenario::class;

        // 1) Категории, у которых уже есть привязанные сценарии — самолинкуем
        //    по каждому уникальному (category_id, project_id), чтобы папка
        //    появлялась в выборках с фильтром по проекту.
        $existing = DB::table('model_has_categories')
            ->where('model_type', $modelType)
            ->select('category_id', 'project_id')
            ->distinct()
            ->get();

        $now = now();
        foreach ($existing as $row) {
            DB::table('model_has_categories')->insertOrIgnore([
                'category_id' => $row->category_id,
                'model_id'    => $row->category_id,
                'model_type'  => $modelType,
                'project_id'  => $row->project_id,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Удаляем только self-link записи (model_id = category_id) для Scenario.
        DB::table('model_has_categories')
            ->where('model_type', Scenario::class)
            ->whereColumn('category_id', 'model_id')
            ->delete();
    }
};
