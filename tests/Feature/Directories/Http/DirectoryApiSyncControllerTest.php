<?php

declare(strict_types=1);

namespace Tests\Feature\Directories\Http;

use Spatie\Permission\PermissionRegistrar;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Directories\Temporal\RunDirectoryImportWorkflowStarterInterface;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\FakeRunDirectoryImportWorkflowStarter;
use Tests\TestCase;

final class DirectoryApiSyncControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_store_queues_sync_for_api_directory_and_returns_202(): void
    {
        $starter = new FakeRunDirectoryImportWorkflowStarter();
        $this->app->instance(RunDirectoryImportWorkflowStarterInterface::class, $starter);

        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project, sourceType: 'api', apiConfig: [
            'endpoint' => 'https://example.com/api/data',
        ]);

        $this->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/sync-api")
            ->assertStatus(202);

        $this->assertDatabaseHas('directory_imports', [
            'directory_id' => $directory->id,
            'source_type' => 'remote',
            'status' => 'processing',
        ]);

        $this->assertCount(1, $starter->pagedCalls);
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
        $directory = $this->makeDirectory($project, sourceType: 'api', apiConfig: [
            'endpoint' => 'https://example.com/api/data',
        ]);

        $this->actingAs($user)
            ->postJson("/api/directories/{$directory->id}/sync-api", [
                'add_new' => true,
                'update_existing' => false,
                'delete_unused' => false,
            ])
            ->assertStatus(202);

        $this->assertCount(1, $starter->pagedCalls);
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
}
