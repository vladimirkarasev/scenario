<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories\Temporal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Temporal\Activities\FinalizeDirectoryImportActivity;
use Module\Projects\Models\Project;
use Tests\TestCase;

final class FinalizeDirectoryImportActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_marks_import_as_completed(): void
    {
        $import = $this->makeImport();

        app(FinalizeDirectoryImportActivity::class)->complete($import->id);

        $this->assertDatabaseHas('directory_imports', [
            'id' => $import->id,
            'status' => DirectoryImportStatus::Completed->value,
        ]);
    }

    public function test_fail_marks_import_as_failed_with_message(): void
    {
        $import = $this->makeImport();

        app(FinalizeDirectoryImportActivity::class)->fail($import->id, 'chunk boom');

        $this->assertDatabaseHas('directory_imports', [
            'id' => $import->id,
            'status' => DirectoryImportStatus::Failed->value,
            'error_message' => 'chunk boom',
        ]);
    }

    private function makeImport(): DirectoryImport
    {
        $project = Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-'.uniqid(),
            'host' => uniqid().'.local',
            'shared_secret' => str_repeat('a', 32),
            'is_active' => true,
        ]);

        $directory = Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Directory',
            'slug' => 'dir-'.uniqid(),
            'source_type' => 'manual',
        ]);

        $version = DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => 1,
            'is_active' => false,
            'source_type' => 'manual',
            'status' => 'draft',
            'schema_json' => [['key' => 'name', 'name' => 'Name', 'type' => 'string']],
        ]);

        return DirectoryImport::query()->create([
            'directory_id' => $directory->id,
            'directory_version_id' => $version->id,
            'mode' => 'create',
            'status' => DirectoryImportStatus::Processing->value,
            'source_type' => 'file',
            'file_disk' => 'local',
            'file_path' => 'test/path.xlsx',
            'mapping_json' => [],
            'fields_json' => [],
            'source_config_json' => [],
            'processed_keys_json' => [],
        ]);
    }
}
