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
            $table->json('nodes_json')->nullable()->after('schema_json');
            $table->json('edges_json')->nullable()->after('nodes_json');
            $table->unsignedInteger('schema_version')->default(1)->after('edges_json');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_versions', static function (Blueprint $table): void {
            $table->dropColumn(['nodes_json', 'edges_json', 'schema_version']);
        });
    }
};
