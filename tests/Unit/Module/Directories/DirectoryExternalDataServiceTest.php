<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Directories\Exceptions\DirectoryExternalException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\DirectoryExternalDataService;
use Module\Projects\Models\Project;
use Module\Proxy\Models\ProxyEndpoint;
use Tests\Stubs\StubProxyHandler;
use Tests\TestCase;

final class DirectoryExternalDataServiceTest extends TestCase
{
    use RefreshDatabase;

    private DirectoryExternalDataService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['proxy.allowed_handlers' => [StubProxyHandler::class]]);

        $this->service = app(DirectoryExternalDataService::class);
    }

    public function test_throws_when_no_active_version(): void
    {
        $directory = $this->makeDirectory();

        $this->expectException(DirectoryExternalException::class);
        $this->expectExceptionMessage('Active directory version not found.');

        $this->service->activeData($directory);
    }

    public function test_throws_when_proxy_not_configured(): void
    {
        $directory = $this->makeDirectory(['api_config_json' => []]);
        $this->makeVersion($directory);

        $this->expectException(DirectoryExternalException::class);
        $this->expectExceptionMessage('External directory has no proxy configured.');

        $this->service->activeData($directory);
    }

    public function test_throws_when_proxy_endpoint_not_found(): void
    {
        $uuid = 'missing-proxy-uuid';
        $directory = $this->makeDirectory(['api_config_json' => ['proxy_uuid' => $uuid]]);
        $this->makeVersion($directory);

        $this->expectException(DirectoryExternalException::class);
        $this->expectExceptionMessage("Proxy endpoint [{$uuid}] not found.");

        $this->service->activeData($directory);
    }

    public function test_returns_mapped_data_from_proxy(): void
    {
        $uuid = 'proxy-happy-path';
        $directory = $this->makeDirectory([
            'api_config_json' => [
                'proxy_uuid' => $uuid,
                'field_mapping' => ['city' => 'town', 'age' => 'years'],
            ],
        ]);
        $version = $this->makeVersion($directory);
        $this->makeEndpoint($uuid, [
            'data' => [
                ['id' => 1, 'town' => 'Moscow', 'years' => '30'],
                ['id' => 2, 'town' => 'Berlin', 'years' => '25'],
            ],
            'meta' => ['total' => 2],
        ]);

        $result = $this->service->activeData($directory);

        $this->assertSame($directory->id, $result->dictionary['id']);
        $this->assertSame($directory->slug, $result->dictionary['code']);
        $this->assertSame($version->id, $result->dictionary['active_version_id']);
        $this->assertSame($version->version_number, $result->dictionary['active_version_number']);
        $this->assertCount(2, $result->items);
        $this->assertSame(['id' => 1, 'city' => 'Moscow', 'age' => '30'], $result->items[0]);
        $this->assertSame(['id' => 2, 'city' => 'Berlin', 'age' => '25'], $result->items[1]);
        $this->assertSame(2, $result->pagination->total);
    }

    public function test_returns_empty_data_when_proxy_response_is_not_array(): void
    {
        $uuid = 'proxy-non-array';
        $directory = $this->makeDirectory(['api_config_json' => ['proxy_uuid' => $uuid]]);
        $this->makeVersion($directory);
        $this->makeEndpoint($uuid, ['data' => 'not_an_array']);

        $result = $this->service->activeData($directory);

        $this->assertSame([], $result->items);
        $this->assertSame(0, $result->pagination->total);
    }

    public function test_field_mapping_skips_entries_with_empty_proxy_field(): void
    {
        $uuid = 'proxy-skip-empty';
        $directory = $this->makeDirectory([
            'api_config_json' => [
                'proxy_uuid' => $uuid,
                'field_mapping' => ['name' => 'full_name', 'skip_me' => ''],
            ],
        ]);
        $this->makeVersion($directory);
        $this->makeEndpoint($uuid, [
            'data' => [['id' => 1, 'full_name' => 'Ivan']],
        ]);

        $result = $this->service->activeData($directory);

        $this->assertSame('Ivan', $result->items[0]['name']);
        $this->assertArrayNotHasKey('skip_me', $result->items[0]);
    }

    public function test_item_id_is_always_included_even_without_mapping(): void
    {
        $uuid = 'proxy-id-only';
        $directory = $this->makeDirectory([
            'api_config_json' => ['proxy_uuid' => $uuid, 'field_mapping' => []],
        ]);
        $this->makeVersion($directory);
        $this->makeEndpoint($uuid, [
            'data' => [['id' => 42, 'name' => 'Ignored']],
        ]);

        $result = $this->service->activeData($directory);

        $this->assertSame(42, $result->items[0]['id']);
        $this->assertArrayNotHasKey('name', $result->items[0]);
    }

    public function test_meta_defaults_to_empty_when_absent_from_response(): void
    {
        $uuid = 'proxy-no-meta';
        $directory = $this->makeDirectory(['api_config_json' => ['proxy_uuid' => $uuid]]);
        $this->makeVersion($directory);
        $this->makeEndpoint($uuid, ['data' => []]);

        $result = $this->service->activeData($directory);

        $this->assertSame(0, $result->pagination->total);
    }

    private function makeDirectory(array $attrs = []): Directory
    {
        $project = Project::query()->create([
            'name' => 'Test Project',
            'sitekey' => uniqid('sk_', true),
            'host' => 'localhost',
            'is_active' => true,
        ]);

        return Directory::query()->create(array_merge([
            'project_id' => $project->id,
            'name' => 'Test Directory',
            'slug' => 'test-dir-'.uniqid(),
            'source_type' => 'external',
            'api_config_json' => ['proxy_uuid' => null],
        ], $attrs));
    }

    private function makeVersion(Directory $directory): DirectoryVersion
    {
        return DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => 1,
            'is_active' => true,
            'source_type' => 'external',
            'status' => 'active',
            'schema_json' => [],
        ]);
    }

    /** @param  array<string, mixed>  $responseBody */
    private function makeEndpoint(string $uuid, array $responseBody): ProxyEndpoint
    {
        $this->app->instance(StubProxyHandler::class, new StubProxyHandler($responseBody));

        return ProxyEndpoint::query()->create([
            'uuid' => $uuid,
            'name' => 'Test Endpoint',
            'code' => 'test-'.$uuid,
            'handler_class' => StubProxyHandler::class,
            'is_active' => true,
        ]);
    }
}
