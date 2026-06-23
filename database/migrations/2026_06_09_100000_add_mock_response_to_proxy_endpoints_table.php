<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxy_endpoints', function (Blueprint $table): void {
            $table->boolean('is_mocked')->default(false)->after('is_active');
            $table->json('mock_responses')->nullable()->after('config');
        });
    }

    public function down(): void
    {
        Schema::table('proxy_endpoints', function (Blueprint $table): void {
            $table->dropColumn(['is_mocked', 'mock_responses']);
        });
    }
};
