<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories\Temporal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Temporal\Activities\PrepareDirectoryImportActivity;
use Module\Projects\Models\Project;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class PrepareDirectoryImportActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepare_computes_chunk_plan_from_excel_file(): void
    {
        $version = $this->makeVersion();
        $path = $this->storeExcelFile([
            ['name'],
            ['Item 1'],
            ['Item 2'],
            ['Item 3'],
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

        $plan = app(PrepareDirectoryImportActivity::class)->prepare($import->id);

        $this->assertSame(1, $plan['headingRow']);
        $this->assertSame(2, $plan['firstDataRow']);
        $this->assertSame(2, $plan['chunkSize']);
        $this->assertSame(4, $plan['totalRows']);
        $this->assertGreaterThan(0, $plan['chunkTimeoutSeconds']);
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

        $tmpPath = sys_get_temp_dir().'/test_prepare_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        $relativePath = 'directory-imports/test/'.uniqid().'.xlsx';
        Storage::disk('local')->put($relativePath, (string)file_get_contents($tmpPath));
        unlink($tmpPath);

        return $relativePath;
    }
}
