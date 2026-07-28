<?php

declare(strict_types=1);

namespace Tests\Feature\Directories\Http;

use App\Http\Middleware\LogHttpRequest;
use Spatie\Permission\PermissionRegistrar;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Temporal\RunDirectoryImportWorkflowStarterInterface;
use Module\Directories\Temporal\RebuildDirectorySearchTextWorkflowStarterInterface;
use Module\Projects\Models\Project;
use Module\Proxy\Models\ProxyEndpoint;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\FakeRunDirectoryImportWorkflowStarter;
use Tests\Stubs\FakeRebuildDirectorySearchTextWorkflowStarter;
use Tests\Stubs\FakeTemporalScheduleSyncer;
use Module\Schedule\Services\TemporalScheduleSyncerInterface;
use Tests\TestCase;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class DirectoryApiSyncControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(LogHttpRequest::class);
        $this->instance(CentrifugoApiInterface::class, $this->createMock(CentrifugoApiInterface::class));
        $this->instance(LoggerInterface::class, new NullLogger());
        $this->instance(
            RebuildDirectorySearchTextWorkflowStarterInterface::class,
            new FakeRebuildDirectorySearchTextWorkflowStarter(),
        );
        $this->instance(TemporalScheduleSyncerInterface::class, new FakeTemporalScheduleSyncer());
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_store_queues_sync_for_api_directory_and_returns_202(): void
    {
        $starter = new FakeRunDirectoryImportWorkflowStarter();
        $this->app->instance(RunDirectoryImportWorkflowStarterInterface::class, $starter);

        [$user, $project] = $this->makeUserWithProject('directory_create');
        $proxy = $this->makeProxyEndpoint();
        $directory = $this->makeDirectory($project, sourceType: 'api', apiConfig: [
            'proxy_uuid' => $proxy->uuid,
        ]);

        $this->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/sync-api")
            ->assertStatus(202);

        $this->assertDatabaseHas('directory_imports', [
            'directory_id' => $directory->id,
            'source_type' => 'proxy',
            'status' => 'processing',
        ]);

        $import = DirectoryImport::query()->where('directory_id', $directory->id)->sole();
        $this->assertTrue($import->source_config_json['add_new']);
        $this->assertTrue($import->source_config_json['update_existing']);
        $this->assertFalse($import->source_config_json['delete_unused']);

        $this->assertCount(1, $starter->calls);
    }

    public function test_store_returns_422_for_non_api_directory(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project, sourceType: 'manual');

        $this->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/sync-api")
            ->assertUnprocessable();
    }

    public function test_store_accepts_sync_options(): void
    {
        $starter = new FakeRunDirectoryImportWorkflowStarter();
        $this->app->instance(RunDirectoryImportWorkflowStarterInterface::class, $starter);

        [$user, $project] = $this->makeUserWithProject('directory_create');
        $proxy = $this->makeProxyEndpoint();
        $directory = $this->makeDirectory($project, sourceType: 'api', apiConfig: [
            'proxy_uuid' => $proxy->uuid,
        ]);

        $this->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/sync-api", [
                'add_new' => true,
                'update_existing' => false,
                'delete_unused' => false,
            ])
            ->assertStatus(202);

        $import = DirectoryImport::query()->where('directory_id', $directory->id)->sole();
        $this->assertTrue($import->source_config_json['add_new']);
        $this->assertFalse($import->source_config_json['update_existing']);
        $this->assertFalse($import->source_config_json['delete_unused']);

        $this->assertCount(1, $starter->calls);
    }

    public function test_store_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project, sourceType: 'api');

        $this->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/sync-api")
            ->assertForbidden();
    }

    public function test_store_returns_404_for_directory_from_other_project(): void
    {
        [$user] = $this->makeUserWithProject('directory_create');
        $other = $this->makeDirectory($this->makeProject(), sourceType: 'api');

        $this->actingAs($user)
            ->postJson("/api/directories/{$other->id}/sync-api")
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

    /** @param  array<string, mixed>  $apiConfig */
    private function makeDirectory(Project $project, string $sourceType = 'manual', array $apiConfig = []): Directory
    {
        return Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Directory '.Str::random(4),
            'slug' => 'dir-'.Str::random(6),
            'source_type' => $sourceType,
            'api_config_json' => $apiConfig ?: null,
        ]);
    }

    private function makeProxyEndpoint(): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'uuid' => (string)Str::uuid(),
            'name' => 'Directory proxy',
            'code' => 'directory-proxy-'.Str::random(6),
            'handler_class' => 'Tests\\Stubs\\StubProxyHandler',
            'is_active' => true,
        ]);
    }
}
