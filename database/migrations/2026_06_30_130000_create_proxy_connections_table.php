<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_connections', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('project_id')->nullable();
            $table->string('name');
            $table->string('credential_type'); // class-string драйвера ProxyCredential
            $table->json('config')->nullable();  // несекретное (base_uri, login, …)
            $table->text('secrets')->nullable();  // encrypted:array
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->index('project_id');
            $table->index('credential_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_connections');
    }
};
