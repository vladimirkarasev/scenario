<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_imports', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('directory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('directory_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode');
            $table->string('status')->default('pending');
            $table->string('file_disk')->default('local');
            $table->string('file_path');
            $table->string('match_by')->nullable();
            $table->unsignedInteger('chunk_size')->default(500);
            $table->json('mapping_json');
            $table->json('fields_json');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['directory_id', 'status']);
            $table->index(['directory_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_imports');
    }
};
