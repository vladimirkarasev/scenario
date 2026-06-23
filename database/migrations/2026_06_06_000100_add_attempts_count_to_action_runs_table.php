<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_runs', static function (Blueprint $table): void {
            $table->unsignedInteger('attempts_count')->default(1)->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('action_runs', static function (Blueprint $table): void {
            $table->dropColumn('attempts_count');
        });
    }
};
