<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directories', function (Blueprint $table): void {
            $table->jsonb('import_settings_json')->nullable()->after('api_config_json');
        });
    }

    public function down(): void
    {
        Schema::table('directories', function (Blueprint $table): void {
            $table->dropColumn('import_settings_json');
        });
    }
};
