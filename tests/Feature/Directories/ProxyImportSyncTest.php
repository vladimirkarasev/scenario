<?php

declare(strict_types=1);

namespace Tests\Feature\Directories;

use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Services\DictionaryApiSyncService;
use Module\Directories\Temporal\RunDirectoryImportWorkflowStarterInterface;
use Module\Projects\Models\Project;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Models\ProxyRequest;
use Tests\Stubs\FakeRunDirectoryImportWorkflowStarter;
use Tests\Stubs\Proxy\TestLeadProxyHandler;
use Tests\Stubs\FakeRebuildDirectorySearchTextWorkflowStarter;
use Tests\Stubs\FakeTemporalScheduleSyncer;
use Module\Directories\Temporal\RebuildDirectorySearchTextWorkflowStarterInterface;
use Module\Schedule\Services\TemporalScheduleSyncerInterface;
use Tests\TestCase;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ProxyImportSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance(CentrifugoApiInterface::class, $this->createMock(CentrifugoApiInterface::class));
        $this->instance(LoggerInterface::class, new NullLogger());
        $this->instance(
            RebuildDirectorySearchTextWorkflowStarterInterface::class,
            new FakeRebuildDirectorySearchTextWorkflowStarter(),
        );
        $this->instance(TemporalScheduleSyncerInterface::class, new FakeTemporalScheduleSyncer());
    }

    public function test_proxy_import_with_mock_creates_items_with_data(): void
    {
        $this->app->instance(
            RunDirectoryImportWorkflowStarterInterface::class,
            new FakeRunDirectoryImportWorkflowStarter(runInline: true),
        );

        $project = $this->makeProject();
        $user = User::factory()->create([
            'sitekey' => $project->sitekey,
            'host' => $project->host,
        ]);

        $endpoint = ProxyEndpoint::query()->create([
            'project_id' => $project->id,
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
            'api_config_json' => [
                'proxy_uuid' => $endpoint->uuid,
                'field_mapping' => ['name' => 'name'],
                'external_key_field' => 'id',
            ],
        ]);

        $version = DirectoryVersion::query()->create([
            'directory_id' => $directory->id,
            'version_number' => 1,
            'is_active' => true,
            'status' => 'active',
            'source_type' => 'api',
            'sync_options' => [
                'add_new' => true,
                'update_existing' => true,
                'delete_unused' => false,
            ],
            'schema_json' => [
                ['key' => 'name', 'name' => 'Name', 'type' => 'string', 'rules' => ['nullable', 'string']],
            ],
        ]);

        /** @var DictionaryApiSyncService $sync */
        $sync = app(DictionaryApiSyncService::class);
        $import = $sync->queue($directory, $user->id);

        $import->refresh();

        $this->assertSame('completed', $import->status, "Import failed: {$import->error_message}");
        $this->assertSame(3, $import->processed_rows);
        $this->assertSame(3, $import->imported_rows);
        $this->assertSame(0, $import->failed_rows);

        $this->assertSame(['name' => 'name'], $import->mapping_json);
        $this->assertSame('id', $import->external_key_field);

        $items = DirectoryItem::query()
            ->where('directory_version_id', $version->id)
            ->orderBy('external_key')
            ->get();

        $this->assertCount(3, $items);
        $this->assertSame('1', $items[0]->external_key);
        $this->assertSame(['name' => 'Belgee'], $items[0]->data_json);
        $this->assertSame(['name' => 'Geely'], $items[1]->data_json);
        $this->assertSame(['name' => 'Chery'], $items[2]->data_json);

        $proxyRequests = ProxyRequest::query()
            ->where('proxy_endpoint_id', $endpoint->id)
            ->get();

        $this->assertCount(1, $proxyRequests);
        $this->assertSame('processed', $proxyRequests[0]->status->value);
        $this->assertSame('INTERNAL', $proxyRequests[0]->request['method']);
        $this->assertSame('directory-import', $proxyRequests[0]->request['user_agent']);

        DirectoryItem::query()
            ->where('directory_version_id', $version->id)
            ->whereIn('external_key', ['1', '2'])
            ->delete();

        $this->assertSame(1, DirectoryItem::query()
            ->where('directory_version_id', $version->id)
            ->count());

        $secondImport = $sync->queue($directory, $user->id);
        $secondImport->refresh();

        $this->assertSame('completed', $secondImport->status, "Import failed: {$secondImport->error_message}");
        $this->assertSame(3, DirectoryItem::query()
            ->where('directory_version_id', $version->id)
            ->count());
        $this->assertSame(
            ['1', '2', '3'],
            DirectoryItem::query()
                ->where('directory_version_id', $version->id)
                ->orderBy('external_key')
                ->pluck('external_key')
                ->all(),
        );
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
