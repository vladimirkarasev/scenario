<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return; // Columns were not created on SQLite (see _300000 SQLite path)
        }

        DB::statement(<<<'SQL'
            ALTER TABLE proxy_requests
                DROP COLUMN IF EXISTS attempt,
                DROP COLUMN IF EXISTS max_attempts,
                DROP COLUMN IF EXISTS parent_request_id,
                DROP COLUMN IF EXISTS next_retry_at
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('This migration cannot be reversed.');
    }
};
