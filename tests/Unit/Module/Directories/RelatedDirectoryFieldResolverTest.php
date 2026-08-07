<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\RelatedDirectoryFieldResolver;
use Module\Projects\Models\Project;
use Tests\TestCase;

final class RelatedDirectoryFieldResolverTest extends TestCase
{
    use RefreshDatabase;

    private RelatedDirectoryFieldResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(RelatedDirectoryFieldResolver::class);
    }

    public function test_resolves_by_id(): void
    {
        $city = $this->makeDirectoryWithItems([
            ['name' => 'Moscow'],
        ]);

        $schema = [$this->relatedField('city_id', $city->id, 'id', '{{ name }}')];
        $rows = [['id' => 1, 'external_key' => null, 'city_id' => (string)$this->firstItemId($city)]];

        $result = $this->resolver->resolve($rows, $schema);

        $this->assertSame('Moscow', $result[0]['city_id']['label']);
        $this->assertSame('Moscow', $result[0]['city_id']['name']);
    }

    public function test_resolves_by_external_key(): void
    {
        $city = $this->makeDirectory();
        $version = $this->makeVersion($city);
        DirectoryItem::query()->create([
            'directory_version_id' => $version->id,
            'external_key' => 'msk',
            'data_json' => ['name' => 'Moscow'],
        ]);

        $schema = [$this->relatedField('city_id', $city->id, 'external_key', '{{ name }}')];
        $rows = [['id' => 1, 'external_key' => null, 'city_id' => 'msk']];

        $result = $this->resolver->resolve($rows, $schema);

        $this->assertSame('Moscow', $result[0]['city_id']['label']);
    }

    public function test_resolves_by_data_field(): void
    {
        $city = $this->makeDirectory();
        $version = $this->makeVersion($city);
        DirectoryItem::query()->create([
            'directory_version_id' => $version->id,
            'data_json' => ['code' => 'MSK', 'name' => 'Moscow'],
        ]);

        $schema = [$this->relatedField('city_code', $city->id, 'code', '{{ name }}')];
        $rows = [['id' => 1, 'external_key' => null, 'city_code' => 'MSK']];

        $result = $this->resolver->resolve($rows, $schema);

        $this->assertSame('Moscow', $result[0]['city_code']['label']);
    }

    public function test_template_merges_source_and_target_fields(): void
    {
        $city = $this->makeDirectory();
        $version = $this->makeVersion($city);
        $item = DirectoryItem::query()->create([
            'directory_version_id' => $version->id,
            'data_json' => ['name' => 'Moscow'],
        ]);

        $schema = [$this->relatedField('city_id', $city->id, 'id', '{{ name }}:{{ city_id }}')];
        $rows = [['id' => 1, 'external_key' => null, 'city_id' => (string)$item->id]];

        $result = $this->resolver->resolve($rows, $schema);

        $this->assertSame("Moscow:{$item->id}", $result[0]['city_id']['label']);
    }

    public function test_no_match_omits_key(): void
    {
        $city = $this->makeDirectory();
        $this->makeVersion($city);

        $schema = [$this->relatedField('city_id', $city->id, 'id', '{{ name }}')];
        $rows = [['id' => 1, 'external_key' => null, 'city_id' => '999']];

        $result = $this->resolver->resolve($rows, $schema);

        $this->assertArrayNotHasKey('city_id', $result[0]);
    }

    public function test_resolves_multiple_related_fields(): void
    {
        $city = $this->makeDirectory();
        $cityVersion = $this->makeVersion($city);
        $cityItem = DirectoryItem::query()->create([
            'directory_version_id' => $cityVersion->id,
            'data_json' => ['name' => 'Moscow'],
        ]);

        $region = $this->makeDirectory();
        $regionVersion = $this->makeVersion($region);
        $regionItem = DirectoryItem::query()->create([
            'directory_version_id' => $regionVersion->id,
            'data_json' => ['name' => 'Central'],
        ]);

        $schema = [
            $this->relatedField('city_id', $city->id, 'id', '{{ name }}'),
            $this->relatedField('region_id', $region->id, 'id', '{{ name }}'),
        ];
        $rows = [[
            'id' => 1,
            'external_key' => null,
            'city_id' => (string)$cityItem->id,
            'region_id' => (string)$regionItem->id,
        ]];

        $result = $this->resolver->resolve($rows, $schema);

        $this->assertSame('Moscow', $result[0]['city_id']['label']);
        $this->assertSame('Central', $result[0]['region_id']['label']);
    }

    /** @return array<string, mixed> */
    private function relatedField(string $key, string $directoryId, string $matchKey, string $template): array
    {
        return [
            'key' => $key,
            'type' => 'related_directory',
            'related_directory_id' => $directoryId,
            'related_match_key' => $matchKey,
            'related_template' => $template,
        ];
    }

    /** @param  array<int, array<string, mixed>>  $items */
    private function makeDirectoryWithItems(array $items): Directory
    {
        $directory = $this->makeDirectory();
        $version = $this->makeVersion($directory);

        foreach ($items as $item) {
            DirectoryItem::query()->create([
                'directory_version_id' => $version->id,
                'data_json' => ['name' => $item['name']],
            ]);
        }

        return $directory;
    }

    private function firstItemId(Directory $directory): int
    {
        $version = $directory->activeVersion()->firstOrFail();

        return $version->items()->firstOrFail()->id;
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
