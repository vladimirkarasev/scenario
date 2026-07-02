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
        Schema::table('categories', function (Blueprint $table): void {
            // Раздел сценариев, помеченный как «рабочая папка»: служит корнем
            // дерева на /workspace/scenarios. На проект ожидается один такой раздел.
            $table->boolean('is_workspace')->default(false)->after('is_system');
        });

        // Преемственность: ранее роль рабочей папки играл системный раздел «Workspace».
        DB::table('categories')
            ->where('is_system', true)
            ->where('name', 'Workspace')
            ->update(['is_workspace' => true]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn('is_workspace');
        });
    }
};
