<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', static function (Blueprint $table): void {
            $table->uuid('active_version_id')->nullable()->after('updated_by');

            $table->foreign('active_version_id')
                ->references('id')
                ->on('scenario_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scenarios', static function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
            $table->dropColumn('active_version_id');
        });
    }
};
