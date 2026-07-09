<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_groups', static function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropUnique(['ext_id']);
            $table->unique(['site_id', 'slug']);
            $table->unique(['site_id', 'ext_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_groups', static function (Blueprint $table): void {
            $table->dropUnique(['site_id', 'slug']);
            $table->dropUnique(['site_id', 'ext_id']);
            $table->unique('slug');
            $table->unique('ext_id');
        });
    }
};
