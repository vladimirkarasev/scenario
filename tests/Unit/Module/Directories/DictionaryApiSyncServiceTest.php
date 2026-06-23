<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Directories\Exceptions\DictionaryApiSyncException;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DictionaryApiSyncService;
use Module\Projects\Models\Project;
use Module\Proxy\Models\ProxyEndpoint;
use Tests\TestCase;

final class DictionaryApiSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private DictionaryApiSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DictionaryApiSyncService::class);
    }

    /**
     * Директория типа manual — queue() должен бросить исключение «source_type must be api».
     */
    public function test_throws_when_directory_is_not_api_type(): void
    {
        $directory = $this->makeDirectory('manual');

        $this->expectException(DictionaryApiSyncException::class);
        $this->expectExceptionMessage('Directory source_type must be api.');

        $this->service->queue($directory);
    }

    /**
     * api-директория с несуществующим proxy_uuid — ожидаем исключение «Proxy endpoint not found».
     */
    public function test_throws_when_proxy_endpoint_not_found(): void
    {
        $missingUuid = 'non-existent-proxy-uuid';
        $directory = $this->makeDirectory('api', ['proxy_uuid' => $missingUuid]);

        $this->expectException(DictionaryApiSyncException::class);
        $this->expectExceptionMessage("Proxy endpoint [{$missingUuid}] not found.");

        $this->service->queue($directory);
    }

    /**
     * api-директория с валидным прокси — создаётся DirectoryImport с source_type=proxy.
     */
    public function test_queues_import_for_api_directory_with_proxy(): void
    {
        $uuid = 'valid-proxy-uuid';
        $directory = $this->makeDirectory('api', ['proxy_uuid' => $uuid]);

        ProxyEndpoint::query()->create([
            'uuid' => $uuid,
            'name' => 'Test Proxy',
            'code' => 'test-proxy',
            'handler_class' => 'Module\\Proxy\\Webhooks\\TestHandler',
            'is_active' => true,
        ]);

        $import = $this->service->queue($directory);

        $this->assertNotNull($import->id);
        $this->assertSame('proxy', $import->source_type);
        $this->assertSame($directory->id, $import->directory_id);
    }

    /** @param array<string, mixed> $apiConfig */
    private function makeDirectory(string $sourceType, array $apiConfig = []): Directory
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
            'slug' => 'test-dir-' . uniqid(),
            'source_type' => $sourceType,
            'api_config_json' => $apiConfig !== [] ? $apiConfig : null,
        ]);
    }
}
