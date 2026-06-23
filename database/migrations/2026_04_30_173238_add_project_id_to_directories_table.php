<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directories', static function (Blueprint $table): void {
            $table->foreignUuid('project_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });

        $fallbackProjectId = DB::table('projects')->orderBy('id')->value('id');

        if ($fallbackProjectId !== null) {
            DB::table('directories')
                ->whereNull('project_id')
                ->update(['project_id' => $fallbackProjectId]);
        }

        Schema::table('directories', static function (Blueprint $table): void {
            $table->dropUnique('directories_slug_unique');
            $table->unique(['project_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('directories', static function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'slug']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
