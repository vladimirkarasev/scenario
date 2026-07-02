<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proxy_endpoints', static function (Blueprint $table): void {
            $table->foreignId('connection_id')->nullable()->after('handler_class')
                ->constrained('proxy_connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proxy_endpoints', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('connection_id');
        });
    }
};
