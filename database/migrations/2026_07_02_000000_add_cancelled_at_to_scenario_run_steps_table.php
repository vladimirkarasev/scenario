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
            $table->timestamp('cancelled_at')->nullable()->after('exited_at');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_run_steps', static function (Blueprint $table): void {
            $table->dropColumn('cancelled_at');
        });
    }
};
