<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Directories\Exceptions\DirectoryVersionException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\DirectoryVersionService;
use Module\Directories\Temporal\RebuildDirectorySearchTextWorkflowStarterInterface;
use Module\Projects\Models\Project;
use Tests\Stubs\FakeRebuildDirectorySearchTextWorkflowStarter;
use Tests\TestCase;

final class DirectoryVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DirectoryVersionService $service;

    private FakeRebuildDirectorySearchTextWorkflowStarter $searchTextRebuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->searchTextRebuilder = new FakeRebuildDirectorySearchTextWorkflowStarter();
        $this->app->instance(RebuildDirectorySearchTextWorkflowStarterInterface::class, $this->searchTextRebuilder);
        $this->service = app(DirectoryVersionService::class);
    }

    public function test_delete_throws_when_version_belongs_to_another_directory(): void
    {
        $directoryA = $this->makeDirectory();
        $directoryB = $this->makeDirectory();
        $version = $this->makeVersion($directoryB);

        $this->expectException(DirectoryVersionException::class);

        $this->service->delete($directoryA, $version);
    }

    public function test_delete_throws_when_version_is_active(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory, isActive: true);

        $this->expectException(DirectoryVersionException::class);
        $this->expectExceptionMessage('Нельзя удалить активную версию.');

        $this->service->delete($directory, $version);
    }

    public function test_delete_removes_non_active_version(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory, isActive: false);

        $this->service->delete($directory, $version);

        $this->assertNull(DirectoryVersion::query()->find($version->id));
    }

    public function test_update_schema_throws_when_version_belongs_to_another_directory(): void
    {
        $directoryA = $this->makeDirectory();
        $directoryB = $this->makeDirectory();
        $version = $this->makeVersion($directoryB);

        $this->expectException(DirectoryVersionException::class);

        $this->service->updateSchema($directoryA, $version, [], null);
    }

    public function test_update_schema_persists_fields_and_match_by(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory);

        $fields = [['key' => 'name', 'name' => 'Name', 'type' => 'string']];
        $this->service->updateSchema($directory, $version, $fields, 'name');

        $version->refresh();
        $directory->refresh();
        $this->assertSame($fields, $version->schema_json);
        $this->assertSame('name', $directory->match_by);
    }

    public function test_update_schema_starts_search_index_workflow_when_searchable_fields_change(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory);
        $version->forceFill([
            'schema_json' => [
                ['key' => 'name', 'name' => 'Name', 'type' => 'string', 'searchable' => false],
            ],
        ])->save();

        $this->service->updateSchema($directory, $version, [
            ['key' => 'name', 'name' => 'Name', 'type' => 'string', 'searchable' => true],
        ], null);

        $this->assertSame([$version->id], $this->searchTextRebuilder->calls);
    }

    public function test_update_schema_does_not_start_search_index_workflow_when_searchable_fields_stay_same(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory);
        $version->forceFill([
            'schema_json' => [
                ['key' => 'name', 'name' => 'Old name', 'type' => 'string', 'searchable' => true],
            ],
        ])->save();

        $this->service->updateSchema($directory, $version, [
            ['key' => 'name', 'name' => 'New name', 'type' => 'string', 'searchable' => true],
        ], null);

        $this->assertSame([], $this->searchTextRebuilder->calls);
    }

    public function test_update_schema_throws_when_related_directory_is_self(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory);

        $this->expectException(DirectoryVersionException::class);
        $this->expectExceptionMessage('Справочник не может ссылаться сам на себя.');

        $this->service->updateSchema($directory, $version, [
            [
                'key' => 'self_ref',
                'name' => 'Self ref',
                'type' => 'related_directory',
                'related_directory_id' => $directory->id,
                'related_match_key' => 'id',
                'related_template' => '{{ name }}',
            ],
        ], null);
    }

    public function test_update_schema_throws_when_related_directory_belongs_to_another_project(): void
    {
        $directory = $this->makeDirectory();
        $otherProjectDirectory = $this->makeDirectory();
        $version = $this->makeVersion($directory);

        $this->expectException(DirectoryVersionException::class);
        $this->expectExceptionMessage('Связанный справочник не найден.');

        $this->service->updateSchema($directory, $version, [
            [
                'key' => 'city_id',
                'name' => 'City',
                'type' => 'related_directory',
                'related_directory_id' => $otherProjectDirectory->id,
                'related_match_key' => 'id',
                'related_template' => '{{ name }}',
            ],
        ], null);
    }

    public function test_activate_throws_when_version_belongs_to_another_directory(): void
    {
        $directoryA = $this->makeDirectory();
        $directoryB = $this->makeDirectory();
        $version = $this->makeVersion($directoryB);

        $this->expectException(DirectoryVersionException::class);

        $this->service->activate($directoryA, $version);
    }

    public function test_activate_makes_version_active_and_deactivates_others(): void
    {
        $directory = $this->makeDirectory();
        $v1 = $this->makeVersion($directory, isActive: true, versionNumber: 1);
        $v2 = $this->makeVersion($directory, isActive: false, versionNumber: 2);

        $this->service->activate($directory, $v2);

        $v1->refresh();
        $v2->refresh();
        $this->assertFalse((bool)$v1->is_active);
        $this->assertTrue((bool)$v2->is_active);
    }

    public function test_update_settings_throws_when_version_belongs_to_another_directory(): void
    {
        $directoryA = $this->makeDirectory();
        $directoryB = $this->makeDirectory();
        $version = $this->makeVersion($directoryB);

        $this->expectException(DirectoryVersionException::class);

        $this->service->updateSettings($directoryA, $version, 'manual');
    }

    public function test_update_code_throws_when_version_belongs_to_another_directory(): void
    {
        $directoryA = $this->makeDirectory();
        $directoryB = $this->makeDirectory();
        $version = $this->makeVersion($directoryB);

        $this->expectException(DirectoryVersionException::class);

        $this->service->updateCode($directoryA, $version, 'some-code');
    }

    private function makeDirectory(): Directory
    {
        $project = Project::query()->create([
            'name' => 'Test',
            'sitekey' => uniqid('sk_', true),
            'host' => 'localhost',
            'is_active' => true,
        ]);

        return Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Dir '.uniqid(),
            'slug' => 'dir-'.uniqid(),
            'source_type' => 'manual',
        ]);
    }

    private function makeVersion(Directory $directory, bool $isActive = false, int $versionNumber = 1): DirectoryVersion
    {
        return DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => $versionNumber,
            'is_active' => $isActive,
            'source_type' => 'manual',
            'status' => $isActive ? 'active' : 'draft',
            'schema_json' => [],
        ]);
    }
}
