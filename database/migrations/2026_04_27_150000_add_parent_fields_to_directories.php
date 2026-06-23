<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directory_items', static function (Blueprint $table): void {
            $table->unsignedBigInteger('parent_id')->nullable()->after('directory_version_id');
            $table->foreign('parent_id')->references('id')->on('directory_items')->nullOnDelete();
        });

        Schema::table('directory_imports', static function (Blueprint $table): void {
            $table->string('parent_key_field')->nullable()->after('match_by');
        });
    }

    public function down(): void
    {
        Schema::table('directory_items', static function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });

        Schema::table('directory_imports', static function (Blueprint $table): void {
            $table->dropColumn('parent_key_field');
        });
    }
};
