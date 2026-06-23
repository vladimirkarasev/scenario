<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directory_versions', static function (Blueprint $table): void {
            $table->jsonb('sync_options')
                ->default('{"add_new":true,"update_existing":true,"delete_unused":false}')
                ->after('source_type');
        });
    }

    public function down(): void
    {
        Schema::table('directory_versions', static function (Blueprint $table): void {
            $table->dropColumn('sync_options');
        });
    }
};
