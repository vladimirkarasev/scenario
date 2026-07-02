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
            $table->string('base_uri')->nullable()->after('method');
            $table->text('credentials')->nullable()->after('base_uri');
        });
    }

    public function down(): void
    {
        Schema::table('proxy_endpoints', function (Blueprint $table): void {
            $table->dropColumn(['base_uri', 'credentials']);
        });
    }
};
