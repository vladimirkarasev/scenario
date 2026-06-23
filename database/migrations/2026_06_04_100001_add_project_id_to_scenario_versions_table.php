<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_versions', static function (Blueprint $table): void {
            $table->uuid('project_id')->nullable()->after('scenario_id');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_versions', static function (Blueprint $table): void {
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
