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
            $this->upSqlite();

            return;
        }

        // 1. Add new UUID columns
        DB::statement('ALTER TABLE directories ADD COLUMN id_new uuid DEFAULT gen_random_uuid() NOT NULL');
        DB::statement('ALTER TABLE directory_versions ADD COLUMN directory_uuid uuid');
        DB::statement('ALTER TABLE directory_imports ADD COLUMN directory_uuid uuid');
        DB::statement('ALTER TABLE directory_import_schedules ADD COLUMN directory_uuid uuid');

        // 2. Fill UUID values for existing directories (id_new already has defaults)

        // 3. Populate directory_uuid in child tables from directories.id_new
        DB::statement('
            UPDATE directory_versions v
            SET directory_uuid = d.id_new
            FROM directories d
            WHERE v.directory_id = d.id
        ');
        DB::statement('
            UPDATE directory_imports i
            SET directory_uuid = d.id_new
            FROM directories d
            WHERE i.directory_id = d.id
        ');
        DB::statement('
            UPDATE directory_import_schedules s
            SET directory_uuid = d.id_new
            FROM directories d
            WHERE s.directory_id = d.id
        ');

        // 4. Drop foreign keys and indices on child tables
        DB::statement('ALTER TABLE directory_versions DROP CONSTRAINT directory_versions_directory_id_foreign');
        DB::statement('ALTER TABLE directory_imports DROP CONSTRAINT directory_imports_directory_id_foreign');
        DB::statement('ALTER TABLE directory_import_schedules DROP CONSTRAINT directory_import_schedules_directory_id_foreign');

        Schema::table('directory_versions', function ($table) {
            $table->dropUnique(['directory_id', 'version_number']);
            $table->dropIndex(['directory_id', 'status']);
        });
        Schema::table('directory_imports', function ($table) {
            $table->dropIndex(['directory_id', 'status']);
        });
        Schema::table('directory_import_schedules', function ($table) {
            $table->dropUnique(['directory_id']);
        });

        // Also drop the is_active index if it exists
        DB::statement("
            DO \$\$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_indexes WHERE tablename = 'directory_versions' AND indexname = 'directory_versions_directory_id_is_active_index') THEN
                    DROP INDEX directory_versions_directory_id_is_active_index;
                END IF;
            END \$\$;
        ");
        DB::statement("
            DO \$\$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_indexes WHERE tablename = 'directory_imports' AND indexname = 'directory_imports_directory_id_source_type_index') THEN
                    DROP INDEX directory_imports_directory_id_source_type_index;
                END IF;
            END \$\$;
        ");

        // 5. Drop old directory_id columns from child tables
        DB::statement('ALTER TABLE directory_versions DROP COLUMN directory_id');
        DB::statement('ALTER TABLE directory_imports DROP COLUMN directory_id');
        DB::statement('ALTER TABLE directory_import_schedules DROP COLUMN directory_id');

        // 6. Rename new uuid columns to directory_id
        DB::statement('ALTER TABLE directory_versions RENAME COLUMN directory_uuid TO directory_id');
        DB::statement('ALTER TABLE directory_imports RENAME COLUMN directory_uuid TO directory_id');
        DB::statement('ALTER TABLE directory_import_schedules RENAME COLUMN directory_uuid TO directory_id');

        // 7. Swap directories primary key: drop old id, promote id_new to id
        DB::statement('ALTER TABLE directories DROP CONSTRAINT directories_pkey');
        DB::statement('ALTER TABLE directories DROP COLUMN id');
        DB::statement('ALTER TABLE directories RENAME COLUMN id_new TO id');
        DB::statement('ALTER TABLE directories ADD PRIMARY KEY (id)');

        // 8. Re-add NOT NULL constraints on child tables
        DB::statement('ALTER TABLE directory_versions ALTER COLUMN directory_id SET NOT NULL');
        DB::statement('ALTER TABLE directory_imports ALTER COLUMN directory_id SET NOT NULL');
        DB::statement('ALTER TABLE directory_import_schedules ALTER COLUMN directory_id SET NOT NULL');

        // 9. Re-add foreign key constraints
        DB::statement('ALTER TABLE directory_versions ADD CONSTRAINT directory_versions_directory_id_foreign FOREIGN KEY (directory_id) REFERENCES directories(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE directory_imports ADD CONSTRAINT directory_imports_directory_id_foreign FOREIGN KEY (directory_id) REFERENCES directories(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE directory_import_schedules ADD CONSTRAINT directory_import_schedules_directory_id_foreign FOREIGN KEY (directory_id) REFERENCES directories(id) ON DELETE CASCADE');

        // 10. Re-add indices
        DB::statement('CREATE UNIQUE INDEX directory_versions_directory_id_version_number_unique ON directory_versions (directory_id, version_number)');
        DB::statement('CREATE INDEX directory_versions_directory_id_status_index ON directory_versions (directory_id, status)');
        DB::statement('CREATE INDEX directory_imports_directory_id_status_index ON directory_imports (directory_id, status)');
        DB::statement('CREATE UNIQUE INDEX directory_import_schedules_directory_id_unique ON directory_import_schedules (directory_id)');
    }

    public function down(): void
    {
        // Reversing a UUID-to-bigint migration for existing data is not practical.
        // This migration is intentionally irreversible.
        throw new RuntimeException('This migration cannot be reversed.');
    }

    private function upSqlite(): void
    {
        // SQLite does not support gen_random_uuid(), DROP CONSTRAINT, or ALTER COLUMN.
        // Since this always runs on a fresh in-memory test DB (no data to preserve),
        // we recreate the four affected tables with the correct UUID-based schema.

        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::dropIfExists('directory_import_schedules');
        Schema::dropIfExists('directory_imports');
        Schema::dropIfExists('directory_versions');
        Schema::dropIfExists('directories');

        Schema::create('directories', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('source_type')->default('manual');
            $table->string('match_by')->nullable();
            $table->text('api_config_json')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            $table->string('sync_status')->default('idle');
            $table->text('sync_error')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
            $table->index(['source_type', 'next_sync_at']);
        });

        Schema::create('directory_versions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('directory_id')->constrained('directories')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status')->default('draft');
            $table->json('schema_json');
            $table->unsignedBigInteger('source_import_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('source_metadata_json')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['directory_id', 'version_number']);
            $table->index(['directory_id', 'status']);
            $table->index(['source_import_id']);
            $table->index(['directory_id', 'is_active']);
        });

        Schema::create('directory_imports', static function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('directory_id')->constrained('directories')->cascadeOnDelete();
            $table->foreignId('directory_version_id')->nullable()->constrained('directory_versions')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode');
            $table->string('status')->default('pending');
            $table->string('source_type')->default('file');
            $table->string('file_disk')->default('local');
            $table->string('file_path');
            $table->string('match_by')->nullable();
            $table->string('parent_key_field')->nullable();
            $table->unsignedInteger('chunk_size')->default(500);
            $table->json('mapping_json');
            $table->json('fields_json');
            $table->json('remote_config_json')->nullable();
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['directory_id', 'status']);
            $table->index(['directory_version_id']);
            $table->index(['directory_id', 'source_type']);
        });

        Schema::create('directory_import_schedules', static function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('directory_id')->constrained('directories')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('frequency')->default('daily');
            $table->time('run_at')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('mode');
            $table->string('match_by')->nullable();
            $table->unsignedInteger('chunk_size')->default(500);
            $table->json('mapping_json');
            $table->json('fields_json');
            $table->json('remote_config_json');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->unique('directory_id');
            $table->index(['enabled', 'next_run_at']);
        });

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
