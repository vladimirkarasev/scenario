<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_credentials', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->json('config')->nullable();
            $table->json('encrypted_secrets')->nullable();
            $table->timestamps();

            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_credentials');
    }
};
