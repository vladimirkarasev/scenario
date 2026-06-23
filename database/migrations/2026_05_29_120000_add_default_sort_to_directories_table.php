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
            $table->string('default_sort')->nullable()->after('match_by');
        });
    }

    public function down(): void
    {
        Schema::table('directories', static function (Blueprint $table): void {
            $table->dropColumn('default_sort');
        });
    }
};
