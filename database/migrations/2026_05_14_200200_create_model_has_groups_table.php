<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_has_groups', static function (Blueprint $table): void {
            $table->uuid('group_id');
            $table->uuid('model_id');
            $table->string('model_type');
            $table->timestamps();

            $table->primary(['group_id', 'model_id', 'model_type']);
            $table->index(['model_type', 'model_id']);

            $table->foreign('group_id')->references('id')->on('user_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_groups');
    }
};
