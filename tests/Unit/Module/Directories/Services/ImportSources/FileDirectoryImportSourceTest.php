<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories\Services\ImportSources;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Module\Directories\DTO\ExcelImportData;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\DTO\StoredExcelFile;
use Module\Directories\DTO\UploadedExcelFiles;
use Module\Directories\Enums\DirectoryImportMode;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\ImportSources\FileDirectoryImportSource;
use Module\Projects\Models\Project;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class FileDirectoryImportSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_payload_computes_chunk_plan_from_excel_file(): void
    {
        $directory = $this->makeDirectory();
        $path = $this->storeExcelFile([
            ['name'],
            ['Item 1'],
            ['Item 2'],
            ['Item 3'],
        ]);

        $data = new ExcelImportData(
            directory: $directory,
            files: new StoredExcelFile('local', $path),
            mode: DirectoryImportMode::Create,
            mapping: ['name' => 'name'],
            fields: [['key' => 'name', 'name' => 'Name', 'type' => 'string']],
            matchBy: null,
            parentKeyField: null,
            chunkSize: 2,
            activate: true,
            options: DirectoryImportOptions::forMode(DirectoryImportMode::Create->value),
            uploadedBy: null,
            versionId: null,
        );

        $payload = app(FileDirectoryImportSource::class)->buildPayload($data);

        $this->assertSame('local', $payload->disk);
        $this->assertSame($path, $payload->path);
        $this->assertSame(2, $payload->config->files[0]->firstDataRow);
        $this->assertSame(4, $payload->config->files[0]->totalRows);
        $this->assertSame(2, $payload->config->totalChunks);
    }

    public function test_fetch_page_reads_disjoint_row_ranges_without_duplicates_or_gaps(): void
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
            'source_config_json' => [
                'files' => [[
                    'disk' => 'local',
                    'path' => $path,
                    'first_data_row' => 2,
                    'total_rows' => 6,
                ]],
                'totalChunks' => 3,
            ],
            'processed_keys_json' => [],
        ]);

        $source = app(FileDirectoryImportSource::class);

        $page1 = $source->fetchPage($import, 1);
        $page2 = $source->fetchPage($import, 2);
        $page3 = $source->fetchPage($import, 3);

        $this->assertTrue($page1->hasMore);
        $this->assertTrue($page2->hasMore);
        $this->assertFalse($page3->hasMore);

        $names = $page1->rows->concat($page2->rows)->concat($page3->rows)
            ->map(static fn(mixed $row): mixed => ($row instanceof Collection ? $row->toArray() : $row)['name'] ?? null)
            ->values()
            ->all();

        $this->assertSame(['Item 1', 'Item 2', 'Item 3', 'Item 4', 'Item 5'], $names);
    }

    public function test_fetch_page_continues_with_next_excel_file(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory);
        $files = [
            $this->makeUploadedExcelFile([['name'], ['First 1'], ['First 2'], ['First 3']], 'first.xlsx'),
            $this->makeUploadedExcelFile([['name'], ['Second 1'], ['Second 2']], 'second.xlsx'),
        ];
        $data = new ExcelImportData(
            directory: $directory,
            files: new UploadedExcelFiles($files),
            mode: DirectoryImportMode::Create,
            mapping: ['name' => 'name'],
            fields: [['key' => 'name', 'name' => 'Name', 'type' => 'string']],
            matchBy: null,
            parentKeyField: null,
            chunkSize: 2,
            activate: true,
            options: DirectoryImportOptions::forMode(DirectoryImportMode::Create->value),
            uploadedBy: null,
            versionId: $version->id,
        );

        $source = app(FileDirectoryImportSource::class);
        $payload = $source->buildPayload($data);
        $import = DirectoryImport::query()->create([
            'directory_id' => $directory->id,
            'directory_version_id' => $version->id,
            'mode' => 'create',
            'status' => DirectoryImportStatus::Processing->value,
            'source_type' => 'file',
            'file_disk' => $payload->disk,
            'file_path' => $payload->path,
            'chunk_size' => 2,
            'mapping_json' => ['name' => 'name'],
            'fields_json' => [['key' => 'name', 'name' => 'Name', 'type' => 'string']],
            'source_config_json' => $payload->config->toArray(),
            'processed_keys_json' => [],
        ]);

        $pages = [
            $source->fetchPage($import, 1),
            $source->fetchPage($import, 2),
            $source->fetchPage($import, 3),
        ];

        $this->assertTrue($pages[0]->hasMore);
        $this->assertTrue($pages[1]->hasMore);
        $this->assertFalse($pages[2]->hasMore);

        $names = collect($pages)
            ->flatMap(static fn($page): Collection => $page->rows)
            ->map(static fn(mixed $row): mixed => ($row instanceof Collection ? $row->toArray() : $row)['name'] ?? null)
            ->values()
            ->all();

        $this->assertSame(['First 1', 'First 2', 'First 3', 'Second 1', 'Second 2'], $names);
    }

    private function makeDirectory(): Directory
    {
        $project = Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-'.uniqid(),
            'host' => uniqid().'.local',
            'shared_secret' => str_repeat('a', 32),
            'is_active' => true,
        ]);

        return Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Directory',
            'slug' => 'dir-'.uniqid(),
            'source_type' => 'manual',
        ]);
    }

    private function makeVersion(?Directory $directory = null): DirectoryVersion
    {
        $directory ??= $this->makeDirectory();

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

        $tmpPath = sys_get_temp_dir().'/test_file_source_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        $relativePath = 'directory-imports/test/'.uniqid().'.xlsx';
        Storage::disk('local')->put($relativePath, (string)file_get_contents($tmpPath));
        unlink($tmpPath);

        return $relativePath;
    }

    /** @param  array<int, list<string>>  $rows */
    private function makeUploadedExcelFile(array $rows, string $name): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = sys_get_temp_dir().'/test_uploaded_file_source_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
