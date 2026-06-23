<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('directory_version_id')->constrained()->cascadeOnDelete();
            $table->string('external_key')->nullable();
            $table->longText('search_text')->nullable();
            $table->timestamps();

            $table->unique(['directory_version_id', 'external_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_items');
    }
};
