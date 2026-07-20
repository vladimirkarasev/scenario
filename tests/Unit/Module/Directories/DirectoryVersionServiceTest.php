<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Module\Directories\Exceptions\DirectoryVersionException;
use Module\Directories\Jobs\RebuildDirectorySearchTextJob;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\DirectoryVersionService;
use Module\Projects\Models\Project;
use Tests\TestCase;

final class DirectoryVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DirectoryVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DirectoryVersionService::class);
    }

    /**
     * Версия принадлежит директории B, удаляем через директорию A — ожидаем исключение
     */
    public function test_delete_throws_when_version_belongs_to_another_directory(): void
    {
        $directoryA = $this->makeDirectory();
        $directoryB = $this->makeDirectory();
        $version = $this->makeVersion($directoryB);

        $this->expectException(DirectoryVersionException::class);

        $this->service->delete($directoryA, $version);
    }

    /**
     * Попытка удалить активную версию — ожидаем исключение с сообщением о запрете
     */
    public function test_delete_throws_when_version_is_active(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory, isActive: true);

        $this->expectException(DirectoryVersionException::class);
        $this->expectExceptionMessage('Нельзя удалить активную версию.');

        $this->service->delete($directory, $version);
    }

    /**
     * Неактивная версия той же директории — должна быть удалена из БД
     */
    public function test_delete_removes_non_active_version(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory, isActive: false);

        $this->service->delete($directory, $version);

        $this->assertNull(DirectoryVersion::query()->find($version->id));
    }

    /**
     * Версия чужой директории — updateSchema должен бросить исключение
     */
    public function test_update_schema_throws_when_version_belongs_to_another_directory(): void
    {
        $directoryA = $this->makeDirectory();
        $directoryB = $this->makeDirectory();
        $version = $this->makeVersion($directoryB);

        $this->expectException(DirectoryVersionException::class);

        $this->service->updateSchema($directoryA, $version, [], null);
    }

    /**
     * Передаём поля схемы и match_by — они должны сохраниться в версии и директории
     */
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

    public function test_update_schema_dispatches_search_index_job_when_searchable_fields_change(): void
    {
        Queue::fake();
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

        Queue::assertPushed(
            RebuildDirectorySearchTextJob::class,
            static fn(RebuildDirectorySearchTextJob $job): bool => $job->directoryVersionId === $version->id
                && $job->queue === 'imports',
        );
    }

    public function test_update_schema_does_not_dispatch_search_index_job_when_searchable_fields_stay_same(): void
    {
        Queue::fake();
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

        Queue::assertNotPushed(RebuildDirectorySearchTextJob::class);
    }

    /**
     * Версия чужой директории — activate должен бросить исключение
     */
    public function test_activate_throws_when_version_belongs_to_another_directory(): void
    {
        $directoryA = $this->makeDirectory();
        $directoryB = $this->makeDirectory();
        $version = $this->makeVersion($directoryB);

        $this->expectException(DirectoryVersionException::class);

        $this->service->activate($directoryA, $version);
    }

    /**
     * Активируем v2 при уже активном v1 — v1 должен стать неактивным, v2 активным
     */
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

    /**
     * Версия чужой директории — updateSettings должен бросить исключение
     */
    public function test_update_settings_throws_when_version_belongs_to_another_directory(): void
    {
        $directoryA = $this->makeDirectory();
        $directoryB = $this->makeDirectory();
        $version = $this->makeVersion($directoryB);

        $this->expectException(DirectoryVersionException::class);

        $this->service->updateSettings($directoryA, $version, 'manual');
    }

    /**
     * Версия чужой директории — updateCode должен бросить исключение
     */
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
