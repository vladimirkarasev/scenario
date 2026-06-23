<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // encrypted:array cast produces a base64 string, incompatible with PostgreSQL json column type
        DB::statement('ALTER TABLE directories ALTER COLUMN api_config_json TYPE text USING api_config_json::text');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE directories ALTER COLUMN api_config_json TYPE json USING NULL');
    }
};
