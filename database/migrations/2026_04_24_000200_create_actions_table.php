<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actions', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('key')->unique();
            $table->text('description')->nullable();
            $table->string('type');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->json('schema')->nullable();
            $table->json('ui_schema')->nullable();
            $table->json('input_fields')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};
