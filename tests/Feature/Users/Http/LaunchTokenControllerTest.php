<?php

declare(strict_types=1);

namespace Tests\Feature\Users\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Module\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class LaunchTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_is_issued_for_non_system_user_in_current_project(): void
    {
        [$actor, $project] = $this->actor();
        $target = User::factory()->create([
            'project_id' => $project->id,
            'external_id' => 'crm-user-1',
            'is_system' => false,
        ]);

        $this->actingAs($actor)
            ->postJson('/api/users/launch-token', ['external_id' => 'crm-user-1'])
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['_token', 'expires_in'],
                'meta' => ['timestamp', 'requestId'],
            ]);

        $this->assertDatabaseHas('launch_tokens', [
            'user_id' => $target->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_user_from_another_project_is_not_visible(): void
    {
        [$actor] = $this->actor();
        $otherProject = $this->project();
        User::factory()->create([
            'project_id' => $otherProject->id,
            'external_id' => 'foreign',
        ]);

        $this->actingAs($actor)
            ->postJson('/api/users/launch-token', ['external_id' => 'foreign'])
            ->assertNotFound();
    }

    public function test_system_user_cannot_receive_launch_token(): void
    {
        [$actor, $project] = $this->actor();
        User::factory()->create([
            'project_id' => $project->id,
            'external_id' => 'system-user',
            'is_system' => true,
        ]);

        $this->actingAs($actor)
            ->postJson('/api/users/launch-token', ['external_id' => 'system-user'])
            ->assertNotFound();
    }

    /** @return array{User, Project} */
    private function actor(): array
    {
        $project = $this->project();
        $actor = User::factory()->create([
            'project_id' => $project->id,
            'sitekey' => $project->sitekey,
            'host' => $project->host,
        ]);
        Permission::query()->create(['name' => 'user_impersonate', 'guard_name' => 'web']);
        $actor->givePermissionTo('user_impersonate');

        return [$actor, $project];
    }

    private function project(): Project
    {
        return Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(6).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => true,
        ]);
    }
}
