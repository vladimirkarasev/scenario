<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_run_steps', static function (Blueprint $table): void {
            // Версия сценария, к которой относится узел шага. Нужна для связных
            // сценариев: один прогон проходит узлы из нескольких версий, и каждый
            // шаг рендерится против своей версии.
            $table->uuid('scenario_version_id')->nullable()->after('run_id');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_run_steps', static function (Blueprint $table): void {
            $table->dropColumn('scenario_version_id');
        });
    }
};
