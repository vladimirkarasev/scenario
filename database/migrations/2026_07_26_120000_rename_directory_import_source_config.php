<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('directory_imports', function (Blueprint $table): void {
            $table->renameColumn('remote_config_json', 'source_config_json');
        });
    }

    public function down(): void
    {
        Schema::table('directory_imports', function (Blueprint $table): void {
            $table->renameColumn('source_config_json', 'remote_config_json');
        });
    }
};
