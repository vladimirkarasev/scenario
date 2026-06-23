<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Доводит дрейфнувшие action-таблицы до канонической схемы:
 *  - actions.input_fields (отсутствовал в части окружений);
 *  - action_schedules.cron (вместо устаревших frequency/run_at).
 *
 * Все проверки через hasColumn — миграция идемпотентна и безопасна на свежей схеме.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('actions') && ! Schema::hasColumn('actions', 'input_fields')) {
            Schema::table('actions', static function (Blueprint $table): void {
                $table->json('input_fields')->nullable();
            });
        }

        if (Schema::hasTable('action_schedules')) {
            if (! Schema::hasColumn('action_schedules', 'cron')) {
                Schema::table('action_schedules', static function (Blueprint $table): void {
                    $table->string('cron')->nullable()->after('enabled');
                });
            }

            $legacy = array_values(array_filter(
                ['frequency', 'run_at'],
                static fn (string $column): bool => Schema::hasColumn('action_schedules', $column),
            ));

            if ($legacy !== []) {
                Schema::table('action_schedules', static function (Blueprint $table) use ($legacy): void {
                    $table->dropColumn($legacy);
                });
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('This migration cannot be reversed.');
    }
};
