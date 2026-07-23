<?php

declare(strict_types=1);

namespace Tests\Feature\Directories\Http;

use Spatie\Permission\PermissionRegistrar;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Temporal\RunDirectoryImportWorkflowStarterInterface;
use Module\Projects\Models\Project;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\FakeRunDirectoryImportWorkflowStarter;
use Tests\TestCase;

final class DirectoryImportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_index_returns_imports_for_directory(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);
        $version = $this->makeVersion($directory);
        $this->makeImport($directory, $version);
        $this->makeImport($directory, $version);

        $this
            ->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/imports")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject();
        $directory = $this->makeDirectory($project);

        $this
            ->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/imports")
            ->assertForbidden();
    }

    public function test_store_queues_excel_import_and_returns_202(): void
    {
        $starter = new FakeRunDirectoryImportWorkflowStarter();
        $this->app->instance(RunDirectoryImportWorkflowStarterInterface::class, $starter);

        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project);
        $file = $this->makeExcelFile([['name'], ['Test Item 1'], ['Test Item 2']]);

        $this
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
            )
            ->assertStatus(202);

        $this->assertDatabaseHas('directory_imports', [
            'directory_id' => $directory->id,
            'source_type' => 'file',
            'mode' => 'create',
            'status' => 'processing',
            'processed_keys_json' => '[]',
        ]);

        $this->assertCount(1, $starter->calls);
    }

    public function test_store_returns_422_when_file_missing_for_file_source(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project);

        $this
            ->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/imports", [
                'source_type' => 'file',
                'mode' => 'create',
                'columns' => [['key' => 'name', 'name' => 'Название']],
                'mapping' => ['0' => 'name'],
            ])
            ->assertUnprocessable();
    }

    public function test_store_returns_422_when_columns_and_mapping_missing(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project);

        $this
            ->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/imports", [
                'source_type' => 'file',
                'mode' => 'create',
            ])
            ->assertUnprocessable();
    }

    public function test_store_returns_422_when_mapping_target_not_in_columns(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project);

        $this
            ->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/imports", [
                'source_type' => 'file',
                'mode' => 'create',
                'columns' => [['key' => 'name', 'name' => 'Название']],
                'mapping' => ['0' => 'non_existent_key'],
            ])
            ->assertUnprocessable();
    }

    public function test_store_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);

        $this
            ->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/imports", [
                'source_type' => 'file',
                'mode' => 'create',
                'columns' => [['key' => 'name', 'name' => 'Название']],
                'mapping' => ['0' => 'name'],
            ])
            ->assertForbidden();
    }

    public function test_store_returns_404_when_directory_from_other_project(): void
    {
        [$user] = $this->makeUserWithProject('directory_create');
        $other = $this->makeDirectory($this->makeProject());
        $file = $this->makeExcelFile([['name'], ['Item 1']]);

        $this
            ->actingAs($user)
            ->post(
                "/api/directories/{$other->id}/imports",
                [
                    'source_type' => 'file',
                    'mode' => 'create',
                    'columns' => [['key' => 'name', 'name' => 'Название']],
                    'mapping' => ['0' => 'name'],
                    'file' => $file,
                ],
                ['Accept' => 'application/json'],
            )
            ->assertNotFound();
    }

    /** @return array{User, Project} */
    private function makeUserWithProject(string ...$permissions): array
    {
        $project = $this->makeProject();
        $user = User::factory()->create([
            'sitekey' => $project->sitekey,
            'host' => $project->host,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return [$user, $project];
    }

    private function makeProject(): Project
    {
        return Project::query()->create([
            'name' => 'Project '.Str::random(4),
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(4).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => true,
        ]);
    }

    private function makeDirectory(Project $project): Directory
    {
        return Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Directory '.Str::random(4),
            'slug' => 'dir-'.Str::random(6),
            'source_type' => 'manual',
        ]);
    }

    private function makeVersion(Directory $directory): DirectoryVersion
    {
        return DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => 1,
            'is_active' => false,
            'source_type' => 'manual',
            'status' => 'draft',
            'schema_json' => [['key' => 'name', 'name' => 'Название', 'type' => 'string']],
        ]);
    }

    private function makeImport(Directory $directory, DirectoryVersion $version): DirectoryImport
    {
        return DirectoryImport::query()->create([
            'directory_id' => $directory->id,
            'directory_version_id' => $version->id,
            'mode' => 'create',
            'status' => 'pending',
            'source_type' => 'file',
            'file_disk' => 'local',
            'file_path' => 'test/path.xlsx',
            'mapping_json' => [],
            'fields_json' => [],
            'remote_config_json' => [],
            'processed_keys_json' => [],
        ]);
    }

    /**
     * @param  array<int, list<string>>  $rows
     */
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
