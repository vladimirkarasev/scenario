<?php

declare(strict_types=1);

namespace Tests\Feature\Directories\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Projects\Models\Project;
use Module\Schedule\Services\TemporalScheduleSyncerInterface;
use Module\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Stubs\FakeTemporalScheduleSyncer;
use Tests\TestCase;

final class DirectorySyncScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private FakeTemporalScheduleSyncer $syncer;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->syncer = new FakeTemporalScheduleSyncer();
        $this->app->instance(TemporalScheduleSyncerInterface::class, $this->syncer);
    }

    public function test_show_returns_null_when_no_schedule_exists(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/sync-schedule")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_upsert_creates_schedule_and_syncs_temporal(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->putJson("/api/directories/{$directory->id}/sync-schedule", [
                'enabled' => true,
                'cron' => '0 * * * *',
                'timezone' => 'UTC',
            ])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.cron', '0 * * * *');

        $this->assertDatabaseHas('schedules', [
            'scope' => 'directory-sync',
            'subject_id' => $directory->id,
            'enabled' => true,
            'cron' => '0 * * * *',
        ]);
        $this->assertCount(1, $this->syncer->upserts);
    }

    public function test_upsert_rejects_invalid_cron(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->putJson("/api/directories/{$directory->id}/sync-schedule", [
                'enabled' => true,
                'cron' => 'not-a-cron',
            ])
            ->assertUnprocessable();
    }

    public function test_destroy_disables_schedule(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->putJson("/api/directories/{$directory->id}/sync-schedule", ['enabled' => true, 'cron' => '0 * * * *']);

        $this->actingAs($user)
            ->deleteJson("/api/directories/{$directory->id}/sync-schedule")
            ->assertNoContent();

        $this->assertDatabaseMissing('schedules', ['scope' => 'directory-sync', 'subject_id' => $directory->id]);
        $this->assertContains("directory-sync-{$directory->id}", $this->syncer->deletedScheduleIds);
    }

    public function test_upsert_returns_404_for_directory_from_other_project(): void
    {
        [$user] = $this->makeUserWithProject('directory_create');
        $other = $this->makeDirectory($this->makeProject());

        $this->actingAs($user)
            ->putJson("/api/directories/{$other->id}/sync-schedule", ['enabled' => true, 'cron' => '0 * * * *'])
            ->assertNotFound();
    }

    public function test_show_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject();
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}/sync-schedule")
            ->assertForbidden();
    }

    public function test_upsert_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject();
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->putJson("/api/directories/{$directory->id}/sync-schedule", ['enabled' => true, 'cron' => '0 * * * *'])
            ->assertForbidden();
    }

    public function test_destroy_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject();
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->deleteJson("/api/directories/{$directory->id}/sync-schedule")
            ->assertForbidden();
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

    private function makeDirectory(Project $project): Directory
    {
        return Directory::query()->create([
            'project_id' => $project->id,
            'name' => 'Directory '.Str::random(4),
            'slug' => 'dir-'.Str::random(6),
            'source_type' => 'api',
        ]);
    }
}
