<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS set_search_vector ON directory_search_entries');
            DB::statement('DROP FUNCTION IF EXISTS update_search_vector()');
        }

        Schema::dropIfExists('directory_search_entries');
    }

    public function down(): void
    {
        // Intentionally not recreating — use git to restore the original migration
    }
};
