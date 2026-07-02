<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actions', static function (Blueprint $table): void {
            $table->renameColumn('key', 'slug');
        });
    }

    public function down(): void
    {
        Schema::table('actions', static function (Blueprint $table): void {
            $table->renameColumn('slug', 'key');
        });
    }
};
