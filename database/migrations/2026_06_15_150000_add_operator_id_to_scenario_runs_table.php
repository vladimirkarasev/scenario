<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_runs', static function (Blueprint $table): void {
            $table->foreignId('operator_id')
                ->nullable()
                ->after('updated_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scenario_runs', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('operator_id');
        });
    }
};
