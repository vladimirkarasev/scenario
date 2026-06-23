<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_versions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('directory_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status')->default('draft');
            $table->json('schema_json');
            $table->unsignedBigInteger('source_import_id')->nullable();
            $table->timestamps();

            $table->unique(['directory_id', 'version_number']);
            $table->index(['directory_id', 'status']);
            $table->index(['source_import_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_versions');
    }
};
