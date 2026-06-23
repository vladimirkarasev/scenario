<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directories', static function (Blueprint $table): void {
            $table->string('source_type')->default('manual')->after('description');
            $table->string('status')->default('active')->after('source_type');
            $table->json('api_config_json')->nullable()->after('match_by');
            $table->timestamp('last_sync_at')->nullable()->after('api_config_json');
            $table->timestamp('next_sync_at')->nullable()->after('last_sync_at');
            $table->string('sync_status')->default('idle')->after('next_sync_at');
            $table->text('sync_error')->nullable()->after('sync_status');

            $table->index(['source_type', 'status']);
            $table->index(['source_type', 'next_sync_at']);
        });

        Schema::table('directory_versions', static function (Blueprint $table): void {
            $table->json('source_metadata_json')->nullable()->after('source_import_id');
        });

        Schema::table('directory_items', static function (Blueprint $table): void {
            $table->json('data_json')->nullable()->after('search_text');
        });
    }

    public function down(): void
    {
        Schema::table('directory_items', static function (Blueprint $table): void {
            $table->dropColumn('data_json');
        });

        Schema::table('directory_versions', static function (Blueprint $table): void {
            $table->dropColumn('source_metadata_json');
        });

        Schema::table('directories', static function (Blueprint $table): void {
            $table->dropIndex(['source_type', 'status']);
            $table->dropIndex(['source_type', 'next_sync_at']);
            $table->dropColumn([
                'source_type',
                'status',
                'api_config_json',
                'last_sync_at',
                'next_sync_at',
                'sync_status',
                'sync_error',
            ]);
        });
    }
};
