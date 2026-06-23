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
                DROP COLUMN IF EXISTS method,
                DROP COLUMN IF EXISTS path,
                DROP COLUMN IF EXISTS ip_address,
                DROP COLUMN IF EXISTS user_agent,
                DROP COLUMN IF EXISTS headers_masked,
                DROP COLUMN IF EXISTS query_params,
                DROP COLUMN IF EXISTS payload,
                DROP COLUMN IF EXISTS message_box,
                DROP COLUMN IF EXISTS response_code,
                DROP COLUMN IF EXISTS response_headers,
                DROP COLUMN IF EXISTS response_body,
                DROP COLUMN IF EXISTS error_message
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('This migration cannot be reversed.');
    }
};
