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
        Schema::table('scenarios', static function (Blueprint $table): void {
            $table->string('status')->default('draft')->after('is_active');
        });

        DB::statement("
            UPDATE scenarios
            SET status = CASE
                WHEN is_active = false THEN 'archived'
                WHEN active_version_id IS NOT NULL THEN 'active'
                ELSE 'draft'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('scenarios', static function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
