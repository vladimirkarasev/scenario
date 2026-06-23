<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Чинит дрейф схемы: в части окружений таблица `actions` (и FK `action_id`
 * в `action_runs` / `action_schedules`) была создана с bigint-id, тогда как
 * модель Action использует HasUuids. Переводим id/FK на uuid.
 *
 * Миграция идемпотентна (no-op, если id уже uuid) и рассчитана на пустые
 * action-таблицы в дрейфнувших окружениях.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // На свежей схеме (в т.ч. sqlite в тестах) id уже uuid.
        }

        if (! Schema::hasTable('actions')) {
            return;
        }

        $idType = DB::selectOne(
            "SELECT data_type FROM information_schema.columns WHERE table_name = 'actions' AND column_name = 'id'",
        );

        if ($idType === null || ($idType->data_type ?? null) === 'uuid') {
            return; // Уже корректная схема — ничего не делаем.
        }

        // Снимаем FK перед сменой типа ключа.
        DB::statement('ALTER TABLE action_runs DROP CONSTRAINT IF EXISTS action_runs_action_id_foreign');
        DB::statement('ALTER TABLE action_schedules DROP CONSTRAINT IF EXISTS action_schedules_action_id_foreign');

        // actions.id: bigint+sequence → uuid (таблица пустая, gen_random_uuid не выполняется на 0 строк).
        DB::statement('ALTER TABLE actions ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE actions ALTER COLUMN id TYPE uuid USING (gen_random_uuid())');

        // FK-колонки → uuid.
        DB::statement('ALTER TABLE action_runs ALTER COLUMN action_id TYPE uuid USING (NULL::uuid)');
        DB::statement('ALTER TABLE action_schedules ALTER COLUMN action_id TYPE uuid USING (NULL::uuid)');

        // Возвращаем FK.
        DB::statement('ALTER TABLE action_runs ADD CONSTRAINT action_runs_action_id_foreign FOREIGN KEY (action_id) REFERENCES actions (id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE action_schedules ADD CONSTRAINT action_schedules_action_id_foreign FOREIGN KEY (action_id) REFERENCES actions (id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        throw new RuntimeException('This migration cannot be reversed.');
    }
};
