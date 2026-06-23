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
        // 1) Колонка nullable, чтобы можно было заполнить существующие записи.
        Schema::table('scenario_runs', static function (Blueprint $table): void {
            $table->bigInteger('number')->nullable()->after('id');
        });

        // 2) Заполняем существующие записи в порядке created_at.
        $rows = DB::table('scenario_runs')->orderBy('created_at')->orderBy('id')->pluck('id');
        $n = 1;
        foreach ($rows as $id) {
            DB::table('scenario_runs')->where('id', $id)->update(['number' => $n++]);
        }

        // 3) PostgreSQL sequence для автоинкремента (на других драйверах, напр. sqlite в тестах,
        //    number проставляется приложением — см. ScenarioRun::booted()).
        if (DB::getDriverName() === 'pgsql') {
            $start = max(1, $n);
            DB::statement('CREATE SEQUENCE IF NOT EXISTS scenario_runs_number_seq OWNED BY scenario_runs.number');
            DB::statement("SELECT setval('scenario_runs_number_seq', {$start}, false)");
            DB::statement("ALTER TABLE scenario_runs ALTER COLUMN number SET DEFAULT nextval('scenario_runs_number_seq')");

            // 4) NOT NULL + UNIQUE (NOT NULL безопасен только при наличии DEFAULT nextval).
            Schema::table('scenario_runs', static function (Blueprint $table): void {
                $table->bigInteger('number')->nullable(false)->change();
            });
        }

        Schema::table('scenario_runs', static function (Blueprint $table): void {
            $table->unique('number');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_runs', static function (Blueprint $table): void {
            $table->dropUnique(['number']);
            $table->dropColumn('number');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS scenario_runs_number_seq');
        }
    }
};
