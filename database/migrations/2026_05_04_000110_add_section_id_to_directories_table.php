<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Module\Directories\Models\DirectorySection;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directories', function (Blueprint $table): void {
            $table->foreignId('section_id')
                ->nullable()
                ->after('project_id')
                ->constrained('directory_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('directories', function (Blueprint $table): void {
            $table->dropForeignIdFor(DirectorySection::class, 'section_id');
            $table->dropColumn('section_id');
        });
    }
};
