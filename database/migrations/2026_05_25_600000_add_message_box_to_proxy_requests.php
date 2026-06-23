<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return; // Column already exists from _300000 SQLite path
        }

        DB::statement('ALTER TABLE proxy_requests ADD COLUMN message_box jsonb NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('proxy_requests', static function (Blueprint $table): void {
                $table->dropColumn('message_box');
            });

            return;
        }

        DB::statement('ALTER TABLE proxy_requests DROP COLUMN IF EXISTS message_box');
    }
};
