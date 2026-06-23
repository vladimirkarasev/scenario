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
        // Перед добавлением unique — устранить дубликаты, дописав суффикс.
        $duplicates = DB::table('scenario_versions')
            ->select('scenario_id', 'name', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('name')
            ->groupBy('scenario_id', 'name')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        foreach ($duplicates as $group) {
            $rows = DB::table('scenario_versions')
                ->where('scenario_id', $group->scenario_id)
                ->where('name', $group->name)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $i = 0;
            foreach ($rows as $row) {
                if ($i > 0) {
                    DB::table('scenario_versions')
                        ->where('id', $row->id)
                        ->update(['name' => $group->name.' ('.$i.')']);
                }
                $i++;
            }
        }

        Schema::table('scenario_versions', static function (Blueprint $table): void {
            $table->unique(['scenario_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('scenario_versions', static function (Blueprint $table): void {
            $table->dropUnique(['scenario_id', 'name']);
        });
    }
};
