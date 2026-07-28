<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Directories\Exceptions\DirectoryVersionException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryCacheRepository;
use Module\Projects\Models\Project;
use Tests\TestCase;
use Module\Directories\DTO\DirectoryQuery;

final class DirectoryCacheRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DirectoryCacheRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(DirectoryCacheRepository::class);
    }

    public function test_throws_when_no_active_version(): void
    {
        $directory = $this->makeDirectory();

        $this->expectException(DirectoryVersionException::class);
        $this->expectExceptionMessage('Active directory version not found.');

        $this->repository->activeData($directory);
    }

    public function test_returns_paginated_items_from_active_version(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory);

        DirectoryItem::query()->create([
            'directory_version_id' => $version->id,
            'data_json' => ['name' => 'Alpha', 'code' => 'A'],
            'search_text' => 'alpha',
        ]);
        DirectoryItem::query()->create([
            'directory_version_id' => $version->id,
            'data_json' => ['name' => 'Beta', 'code' => 'B'],
            'search_text' => 'beta',
        ]);

        $result = $this->repository->activeData($directory);

        $this->assertSame($directory->id, $result->dictionary['id']);
        $this->assertSame($directory->slug, $result->dictionary['code']);
        $this->assertSame($version->id, $result->dictionary['active_version_id']);
        $this->assertCount(2, $result->items);
        $this->assertSame(2, $result->pagination->total);
    }

    public function test_filters_rows_by_field_value(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory);

        DirectoryItem::query()->create([
            'directory_version_id' => $version->id,
            'data_json' => ['name' => 'Alpha'],
            'search_text' => 'alpha',
        ]);
        DirectoryItem::query()->create([
            'directory_version_id' => $version->id,
            'data_json' => ['name' => 'Beta'],
            'search_text' => 'beta',
        ]);

        $result = $this->repository->activeData($directory, DirectoryQuery::fromArray([
            'filter' => ['name' => 'Alpha'],
        ]));

        $this->assertCount(1, $result->items);
        $this->assertSame('Alpha', $result->items[0]['name']);
    }

    public function test_paginates_correctly(): void
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory);

        for ($i = 1; $i <= 5; $i++) {
            DirectoryItem::query()->create([
                'directory_version_id' => $version->id,
                'data_json' => ['seq' => $i],
                'search_text' => (string)$i,
            ]);
        }

        $result = $this->repository->activeData($directory, DirectoryQuery::fromArray(['per_page' => 2, 'page' => 2]));

        $this->assertSame(5, $result->pagination->total);
        $this->assertSame(2, $result->pagination->perPage);
        $this->assertSame(2, $result->pagination->currentPage);
        $this->assertCount(2, $result->items);
    }

    public function test_returns_empty_data_when_version_has_no_items(): void
    {
        $directory = $this->makeDirectory();
        $this->makeVersion($directory);

        $result = $this->repository->activeData($directory);

        $this->assertSame([], $result->items);
        $this->assertSame(0, $result->pagination->total);
    }

    private function makeDirectory(): Directory
    {
        $project = Project::query()->create([
            'name' => 'Test Project',
            'sitekey' => uniqid('sk_', true),
            'host' => 'localhost',
            'is_active' => true,
        ]);

        return Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Test Directory',
            'slug' => 'test-dir-'.uniqid(),
            'source_type' => 'manual',
        ]);
    }

    private function makeVersion(Directory $directory): DirectoryVersion
    {
        return DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => 1,
            'is_active' => true,
            'source_type' => 'manual',
            'status' => 'active',
            'schema_json' => [
                ['key' => 'name', 'name' => 'Name', 'type' => 'string'],
            ],
        ]);
    }
}
