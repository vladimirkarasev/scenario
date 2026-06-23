<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sequence/DEFAULT — только PostgreSQL; на прочих драйверах (sqlite в тестах)
        // number проставляется приложением (см. ScenarioRun::booted()).
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Создать sequence если её ещё нет (на случай свежей БД).
        DB::statement('CREATE SEQUENCE IF NOT EXISTS scenario_runs_number_seq OWNED BY scenario_runs.number');

        // Синхронизировать sequence с максимальным значением, чтобы избежать
        // конфликта при следующем nextval.
        $max = (int) (DB::scalar('SELECT COALESCE(MAX(number), 0) FROM scenario_runs') ?? 0);
        $next = $max + 1;
        DB::statement("SELECT setval('scenario_runs_number_seq', {$next}, false)");

        // Главное: вернуть DEFAULT nextval — `->change()` в исходной
        // миграции сбросил его при выставлении NOT NULL.
        DB::statement("ALTER TABLE scenario_runs ALTER COLUMN number SET DEFAULT nextval('scenario_runs_number_seq')");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE scenario_runs ALTER COLUMN number DROP DEFAULT');
        }
    }
};
