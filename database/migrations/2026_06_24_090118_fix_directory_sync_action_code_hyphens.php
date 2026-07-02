<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Служебные экшены синхронизации справочников создавались с code/slug вида
 * `directory_sync_<uuid>` — дефисы UUID нарушают регулярку ActionRequest `^[a-z][a-z0-9_]*$`,
 * из-за чего редактирование такого экшена падало с 422 "The code field format is invalid".
 * Заменяем дефисы на подчёркивания у уже существующих записей.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('actions')
            ->where('code', 'like', 'directory_sync_%')
            ->update([
                'code' => DB::raw("REPLACE(code, '-', '_')"),
                'slug' => DB::raw("REPLACE(slug, '-', '_')"),
            ]);
    }

    public function down(): void
    {
        // Необратимо: восстановить исходные дефисы по позиции невозможно безопасно.
    }
};
