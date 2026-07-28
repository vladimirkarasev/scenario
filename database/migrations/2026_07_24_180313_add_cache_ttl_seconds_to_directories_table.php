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
            $table->unsignedInteger('cache_ttl_seconds')->nullable()->after('sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('directories', function (Blueprint $table): void {
            $table->dropColumn('cache_ttl_seconds');
        });
    }
};
