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
            return; // Table is dropped in _120000; skip PostgreSQL-specific creation on SQLite
        }

        Schema::create('directory_search_entries', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('item_uuid')->unique();
            $table->foreignId('directory_item_id')->unique()->constrained('directory_items')->cascadeOnDelete();
            $table->foreignId('directory_version_id')->constrained('directory_versions')->cascadeOnDelete()->index();
            $table->text('content')->default('');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE directory_search_entries ADD COLUMN search_vector tsvector');

        DB::statement("
            CREATE OR REPLACE FUNCTION directory_search_entries_update_vector()
            RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                NEW.search_vector := to_tsvector('simple', COALESCE(NEW.content, ''));
                RETURN NEW;
            END;
            \$\$
        ");

        DB::statement('
            CREATE TRIGGER directory_search_entries_vector_trigger
            BEFORE INSERT OR UPDATE OF content ON directory_search_entries
            FOR EACH ROW EXECUTE FUNCTION directory_search_entries_update_vector()
        ');

        DB::statement('CREATE INDEX directory_search_entries_search_vector_idx ON directory_search_entries USING GIN(search_vector)');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS directory_search_entries_vector_trigger ON directory_search_entries');
        DB::statement('DROP FUNCTION IF EXISTS directory_search_entries_update_vector');
        Schema::dropIfExists('directory_search_entries');
    }
};
