<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            // from_address — ключ резолва: отправка выбирается по адресу "От".
            $table->string('from_address')->unique();
            $table->string('from_name')->nullable();
            // driver — транспорт отправки (smtp сейчас, proxy в будущем).
            $table->string('driver')->default('smtp');
            // settings — параметры транспорта (smtp: host/port/username/password/encryption; proxy: endpoint/token).
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};
