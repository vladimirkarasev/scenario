<?php

declare(strict_types=1);

namespace Tests\Feature\Directories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Temporal\RunDirectoryImportWorkflowStarterInterface;
use Module\Projects\Models\Project;
use Module\Users\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Stubs\FakeRunDirectoryImportWorkflowStarter;
use Tests\TestCase;

final class DirectoryImportTemporalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_file_import_starts_temporal_workflow_instead_of_running_inline(): void
    {
        $starter = new FakeRunDirectoryImportWorkflowStarter();
        $this->app->instance(RunDirectoryImportWorkflowStarterInterface::class, $starter);

        $project = Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-temporal',
            'host' => 'temporal.local',
            'shared_secret' => str_repeat('a', 32),
            'is_active' => true,
        ]);
        $user = User::factory()->create(['sitekey' => $project->sitekey, 'host' => $project->host]);
        Permission::firstOrCreate(['name' => 'directory_create', 'guard_name' => 'web']);
        $user->givePermissionTo('directory_create');

        $directory = Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Directory',
            'slug' => 'dir-temporal',
            'source_type' => 'excel',
        ]);

        $file = $this->makeExcelFile([['name'], ['Item 1'], ['Item 2']]);

        $response = $this
            ->actingAs($user)
            ->post(
                "/api/directories/{$directory->id}/imports",
                [
                    'source_type' => 'file',
                    'mode' => 'create',
                    'columns' => [['key' => 'name', 'name' => 'Название']],
                    'mapping' => ['0' => 'name'],
                    'file' => $file,
                ],
                ['Accept' => 'application/json'],
            );

        $response->assertStatus(202);

        $importId = (int)DirectoryImport::query()->where('directory_id', $directory->id)->value('id');

        $this->assertCount(1, $starter->calls);
        $this->assertSame($importId, $starter->calls[0]->directoryImportId);

        $this->assertDatabaseHas('directory_imports', [
            'id' => $importId,
            'status' => DirectoryImportStatus::Processing->value,
        ]);
    }

    /** @param  array<int, list<string>>  $rows */
    private function makeExcelFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = sys_get_temp_dir().'/test_import_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
