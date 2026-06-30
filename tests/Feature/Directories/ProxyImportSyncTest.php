<?php

declare(strict_types=1);

namespace Tests\Feature\Directories;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\DictionaryApiSyncService;
use Module\Projects\Models\Project;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Models\ProxyRequest;
use Module\Proxy\Proxies\Test\TestLeadProxyHandler;
use Tests\TestCase;

/**
 * Полный flow: api-справочник + замоканный proxy endpoint → синхронизация должна
 * подтянуть `items` из mock body, сохранить их в `directory_items` с заполненным
 * `data_json`, а в `proxy_requests` появиться запись с typom `INTERNAL`.
 */
final class ProxyImportSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_proxy_import_with_mock_creates_items_with_data(): void
    {
        // ─── Arrange ────────────────────────────────────────────────────────────
        $project = $this->makeProject();
        $user = User::factory()->create([
            'sitekey' => $project->sitekey,
            'host' => $project->host,
        ]);

        $endpoint = ProxyEndpoint::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Test Brands Mock',
            'code' => 'test-brands',
            'is_active' => true,
            'is_mocked' => true,
            'handler_class' => TestLeadProxyHandler::class,
            'mock_responses' => [
                [
                    'name' => 'brands',
                    'status' => 202,
                    'body' => [
                        'items' => [
                            ['id' => 1, 'name' => 'Belgee'],
                            ['id' => 2, 'name' => 'Geely'],
                            ['id' => 3, 'name' => 'Chery'],
                        ],
                    ],
                    'headers' => null,
                    'is_active' => true,
                ],
            ],
        ]);

        $directory = Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Brands',
            'slug' => 'brands',
            'source_type' => 'api',
            'match_by' => 'id',
            'api_config_json' => [
                'proxy_uuid' => $endpoint->uuid,
                // намеренно НЕ указываем field_mapping — должен сработать fallback 1:1
            ],
        ]);

        $version = DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => 1,
            'is_active' => true,
            'status' => 'active',
            'source_type' => 'api',
            'schema_json' => [
                ['key' => 'id', 'name' => 'ID', 'type' => 'string', 'rules' => ['nullable', 'string']],
                ['key' => 'name', 'name' => 'Name', 'type' => 'string', 'rules' => ['nullable', 'string']],
            ],
        ]);

        // ─── Act ────────────────────────────────────────────────────────────────
        /** @var DictionaryApiSyncService $sync */
        $sync = app(DictionaryApiSyncService::class);
        $import = $sync->queue($directory, $user->id);
        $sync->runImport($import->id);

        // ─── Assert ─────────────────────────────────────────────────────────────
        $import->refresh();

        $this->assertSame('completed', $import->status, "Import failed: {$import->error_message}");
        $this->assertSame(3, $import->processed_rows);
        $this->assertSame(3, $import->imported_rows);
        $this->assertSame(0, $import->failed_rows);

        // mapping_json fallback должен быть 1:1
        $this->assertSame(['id' => 'id', 'name' => 'name'], $import->mapping_json);

        // items записались с заполненным data_json
        $items = DirectoryItem::query()
            ->where('directory_version_id', $version->id)
            ->orderBy('external_key')
            ->get();

        $this->assertCount(3, $items);
        $this->assertSame('1', $items[0]->external_key);
        $this->assertSame(['id' => '1', 'name' => 'Belgee'], $items[0]->data_json);
        $this->assertSame(['id' => '2', 'name' => 'Geely'], $items[1]->data_json);
        $this->assertSame(['id' => '3', 'name' => 'Chery'], $items[2]->data_json);

        // proxy_requests получил запись от внутреннего вызова
        $proxyRequests = ProxyRequest::query()
            ->where('proxy_endpoint_id', $endpoint->id)
            ->get();

        $this->assertCount(1, $proxyRequests);
        $this->assertSame('processed', $proxyRequests[0]->status->value);
        $this->assertSame('INTERNAL', $proxyRequests[0]->request['method']);
        $this->assertSame('directory-import', $proxyRequests[0]->request['user_agent']);
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
}
