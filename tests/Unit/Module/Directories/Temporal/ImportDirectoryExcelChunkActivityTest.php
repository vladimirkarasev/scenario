<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories\Temporal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Temporal\Activities\ImportDirectoryExcelChunkActivity;
use Module\Projects\Models\Project;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class ImportDirectoryExcelChunkActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_chunks_import_disjoint_row_ranges_without_duplicates_or_gaps(): void
    {
        $version = $this->makeVersion();
        $path = $this->storeExcelFile([
            ['name'],
            ['Item 1'],
            ['Item 2'],
            ['Item 3'],
            ['Item 4'],
            ['Item 5'],
        ]);

        $import = DirectoryImport::query()->create([
            'directory_id' => $version->directory_id,
            'directory_version_id' => $version->id,
            'mode' => 'create',
            'status' => DirectoryImportStatus::Processing->value,
            'source_type' => 'file',
            'file_disk' => 'local',
            'file_path' => $path,
            'chunk_size' => 2,
            'mapping_json' => ['name' => 'name'],
            'fields_json' => [['key' => 'name', 'name' => 'Name', 'type' => 'string']],
            'remote_config_json' => [],
            'processed_keys_json' => [],
        ]);

        $activity = app(ImportDirectoryExcelChunkActivity::class);
        $activity->importChunk($import->id, 2, 2);
        $activity->importChunk($import->id, 4, 2);
        $activity->importChunk($import->id, 6, 2);

        $names = DirectoryItem::query()
            ->where('directory_version_id', $version->id)
            ->get()
            ->map(static fn(DirectoryItem $item): mixed => $item->data_json['name'] ?? null)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Item 1', 'Item 2', 'Item 3', 'Item 4', 'Item 5'], $names);
    }

    public function test_skips_processing_when_import_is_not_in_processing_status(): void
    {
        $version = $this->makeVersion();
        $path = $this->storeExcelFile([['name'], ['Item 1']]);

        $import = DirectoryImport::query()->create([
            'directory_id' => $version->directory_id,
            'directory_version_id' => $version->id,
            'mode' => 'create',
            'status' => DirectoryImportStatus::Completed->value,
            'source_type' => 'file',
            'file_disk' => 'local',
            'file_path' => $path,
            'chunk_size' => 2,
            'mapping_json' => ['name' => 'name'],
            'fields_json' => [['key' => 'name', 'name' => 'Name', 'type' => 'string']],
            'remote_config_json' => [],
            'processed_keys_json' => [],
        ]);

        app(ImportDirectoryExcelChunkActivity::class)->importChunk($import->id, 2, 2);

        $this->assertSame(0, DirectoryItem::query()->where('directory_version_id', $version->id)->count());
    }

    private function makeVersion(): DirectoryVersion
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

        return DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => 1,
            'is_active' => false,
            'source_type' => 'manual',
            'status' => 'draft',
            'schema_json' => [['key' => 'name', 'name' => 'Name', 'type' => 'string']],
        ]);
    }

    /** @param  array<int, list<string>>  $rows */
    private function storeExcelFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
            }
        }

        $tmpPath = sys_get_temp_dir().'/test_chunk_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        $relativePath = 'directory-imports/test/'.uniqid().'.xlsx';
        Storage::disk('local')->put($relativePath, (string)file_get_contents($tmpPath));
        unlink($tmpPath);

        return $relativePath;
    }
}
