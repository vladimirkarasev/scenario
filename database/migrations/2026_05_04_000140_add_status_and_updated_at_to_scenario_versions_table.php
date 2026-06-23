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
            $table->string('status')->default('draft')->after('name');
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_versions', static function (Blueprint $table): void {
            $table->dropColumn(['status', 'updated_at']);
        });
    }
};
