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
            $table->dropIndex(['source_type', 'status']);
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('directories', static function (Blueprint $table): void {
            $table->string('status')->default('active')->after('source_type');
            $table->index(['source_type', 'status']);
        });
    }
};
