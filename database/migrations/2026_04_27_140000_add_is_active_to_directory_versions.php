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
            $table->boolean('is_active')->default(false)->after('source_import_id');
            $table->index(['directory_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('directory_versions', static function (Blueprint $table): void {
            $table->dropIndex(['directory_id', 'is_active']);
            $table->dropColumn('is_active');
        });
    }
};
