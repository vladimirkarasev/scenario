<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_item_values', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('directory_item_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('field_name');
            $table->longText('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['directory_item_id', 'field_key']);
            $table->index(['field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_item_values');
    }
};
