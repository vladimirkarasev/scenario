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
            // Снапшот состояния исполнения на момент шага — для корректного отката
            // (jump) в связных сценариях: ревизия версии + стек вызовов.
            $table->uuid('scenario_version_revision_id')->nullable()->after('scenario_version_id');
            $table->json('call_stack')->nullable()->after('scenario_version_revision_id');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_run_steps', static function (Blueprint $table): void {
            $table->dropColumn(['scenario_version_revision_id', 'call_stack']);
        });
    }
};
