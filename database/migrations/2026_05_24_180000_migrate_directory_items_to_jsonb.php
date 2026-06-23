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

        // Populate data_json from directory_item_values where missing
        DB::statement('
            UPDATE directory_items di
            SET data_json = (
                SELECT jsonb_object_agg(dv.field_key, dv.value ORDER BY dv.sort_order)
                FROM directory_item_values dv
                WHERE dv.directory_item_id = di.id
            )
            WHERE di.data_json IS NULL
              AND EXISTS (SELECT 1 FROM directory_item_values WHERE directory_item_id = di.id)
        ');

        // Convert json → jsonb
        DB::statement('ALTER TABLE directory_items ALTER COLUMN data_json TYPE jsonb USING data_json::text::jsonb');

        DB::statement('DROP TABLE IF EXISTS directory_item_values');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE directory_items ALTER COLUMN data_json TYPE json USING data_json::text::json');
    }
};
