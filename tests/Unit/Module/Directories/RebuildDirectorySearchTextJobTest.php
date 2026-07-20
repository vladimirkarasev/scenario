<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Directories\Jobs\RebuildDirectorySearchTextJob;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\DirectoryItemService;
use Module\Projects\Models\Project;
use Tests\TestCase;

final class RebuildDirectorySearchTextJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rebuilds_search_text_from_searchable_fields(): void
    {
        $project = Project::query()->create([
            'name' => 'Test',
            'sitekey' => 'search-index-test',
            'host' => 'localhost',
            'is_active' => true,
        ]);
        $directory = Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Directory',
            'slug' => 'search-index-directory',
            'source_type' => 'manual',
        ]);
        $version = DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => 1,
            'is_active' => true,
            'source_type' => 'manual',
            'status' => 'active',
            'schema_json' => [
                ['key' => 'name', 'name' => 'Name', 'type' => 'string', 'searchable' => true],
                ['key' => 'code', 'name' => 'Code', 'type' => 'string', 'searchable' => false],
            ],
        ]);
        $item = DirectoryItem::query()->create([
            'directory_version_id' => $version->id,
            'external_key' => 'item-1',
            'data_json' => ['name' => 'Новый Элемент', 'code' => 'SECRET-CODE'],
            'search_text' => 'старый индекс',
        ]);

        (new RebuildDirectorySearchTextJob($version->id))->handle(app(DirectoryItemService::class));

        $item->refresh();
        $this->assertSame('новый элемент', $item->search_text);
    }

    public function test_it_finishes_safely_when_version_was_deleted(): void
    {
        (new RebuildDirectorySearchTextJob(999999))->handle(app(DirectoryItemService::class));

        $this->addToAssertionCount(1);
    }
}
