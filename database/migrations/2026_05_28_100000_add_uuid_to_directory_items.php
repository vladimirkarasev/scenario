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
            // Column is added and then immediately dropped in _110000; skip on SQLite
            return;
        }

        DB::statement('ALTER TABLE directory_items ADD COLUMN IF NOT EXISTS uuid uuid');
        DB::statement('UPDATE directory_items SET uuid = gen_random_uuid() WHERE uuid IS NULL');
        DB::statement('ALTER TABLE directory_items ALTER COLUMN uuid SET NOT NULL');
        DB::statement('ALTER TABLE directory_items ALTER COLUMN uuid SET DEFAULT gen_random_uuid()');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS directory_items_uuid_unique ON directory_items (uuid)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('directory_items', static function (Blueprint $table): void {
                if (Schema::hasColumn('directory_items', 'uuid')) {
                    $table->dropColumn('uuid');
                }
            });

            return;
        }

        DB::statement('DROP INDEX IF EXISTS directory_items_uuid_unique');
        DB::statement('ALTER TABLE directory_items DROP COLUMN IF EXISTS uuid');
    }
};
