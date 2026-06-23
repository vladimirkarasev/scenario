<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_version_revisions', static function (Blueprint $table): void {
            $table->json('input_fields')->nullable()->after('edges_json');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_version_revisions', static function (Blueprint $table): void {
            $table->dropColumn('input_fields');
        });
    }
};
