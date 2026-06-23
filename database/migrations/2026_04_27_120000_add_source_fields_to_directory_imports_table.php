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
            $table->string('source_type')->default('file')->after('status');
            $table->json('remote_config_json')->nullable()->after('fields_json');

            $table->index(['directory_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::table('directory_imports', static function (Blueprint $table): void {
            $table->dropIndex(['directory_id', 'source_type']);
            $table->dropColumn(['source_type', 'remote_config_json']);
        });
    }
};
