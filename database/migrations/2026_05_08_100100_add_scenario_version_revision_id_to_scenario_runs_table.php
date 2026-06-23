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
            $table->uuid('scenario_version_revision_id')->nullable()->after('scenario_version_id');

            $table->foreign('scenario_version_revision_id')
                ->references('id')
                ->on('scenario_version_revisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scenario_runs', static function (Blueprint $table): void {
            $table->dropForeign(['scenario_version_revision_id']);
            $table->dropColumn('scenario_version_revision_id');
        });
    }
};
