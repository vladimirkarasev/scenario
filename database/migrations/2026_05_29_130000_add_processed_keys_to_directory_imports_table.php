<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directory_imports', static function (Blueprint $table): void {
            $table->json('processed_keys_json')->nullable()->after('remote_config_json');
        });
    }

    public function down(): void
    {
        Schema::table('directory_imports', static function (Blueprint $table): void {
            $table->dropColumn('processed_keys_json');
        });
    }
};
