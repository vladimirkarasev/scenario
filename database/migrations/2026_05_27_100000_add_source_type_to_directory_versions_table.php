<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directory_versions', static function (Blueprint $table): void {
            $table->string('source_type', 20)->default('manual')->after('is_active');
        });

        // Copy source_type from parent directory to each version
        DB::statement(
            'UPDATE directory_versions
             SET source_type = COALESCE(d.source_type, \'manual\')
             FROM directories d
             WHERE d.id = directory_versions.directory_id'
        );
    }

    public function down(): void
    {
        Schema::table('directory_versions', static function (Blueprint $table): void {
            $table->dropColumn('source_type');
        });
    }
};
