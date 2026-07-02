<?php

declare(strict_types=1);

namespace Tests\Feature\Users\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Module\Users\Models\Role;
use Module\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_is_created_with_allowed_permissions(): void
    {
        $actor = $this->actor('role_create', 'user_view');

        $this->actingAs($actor)
            ->postJson('/api/roles', [
                'name' => 'viewer',
                'title' => 'Viewer',
                'permissions' => ['user_view'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.name', 'viewer');

        $this->assertTrue(Role::query()->where('name', 'viewer')->firstOrFail()->hasPermissionTo('user_view'));
    }

    public function test_role_cannot_grant_permission_actor_does_not_have(): void
    {
        $actor = $this->actor('role_create');
        Permission::firstOrCreate(['name' => 'project_delete', 'guard_name' => 'web']);

        $this->actingAs($actor)
            ->postJson('/api/roles', [
                'name' => 'escalation',
                'permissions' => ['project_delete'],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'escalation']);
    }

    public function test_system_role_cannot_be_updated_or_deleted(): void
    {
        $actor = $this->actor('role_create', 'role_delete');
        $role = Role::query()->create([
            'name' => 'project-service',
            'guard_name' => 'web',
            'is_system' => true,
        ]);

        $this->actingAs($actor)
            ->putJson("/api/roles/{$role->id}", [
                'name' => $role->name,
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->deleteJson("/api/roles/{$role->id}")
            ->assertForbidden();
    }

    public function test_assigned_role_cannot_be_deleted(): void
    {
        $actor = $this->actor('role_delete');
        $role = Role::query()->create(['name' => 'assigned', 'guard_name' => 'web']);
        $actor->assignRole($role);

        $this->actingAs($actor)
            ->deleteJson("/api/roles/{$role->id}")
            ->assertUnprocessable();
    }

    public function test_patch_is_not_exposed_for_role_update(): void
    {
        $actor = $this->actor('role_create');
        $role = Role::query()->create(['name' => 'role-to-patch', 'guard_name' => 'web']);

        $this->actingAs($actor)
            ->patchJson("/api/roles/{$role->id}", ['name' => 'changed'])
            ->assertMethodNotAllowed();
    }

    public function test_less_privileged_actor_cannot_downgrade_powerful_role(): void
    {
        $actor = $this->actor('role_create');
        $permission = Permission::firstOrCreate([
            'name' => 'project_delete',
            'guard_name' => 'web',
        ]);
        $role = Role::query()->create(['name' => 'powerful', 'guard_name' => 'web']);
        $role->syncPermissions([$permission]);

        $this->actingAs($actor)
            ->putJson("/api/roles/{$role->id}", [
                'name' => 'powerful',
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->assertTrue($role->fresh()->hasPermissionTo('project_delete'));
    }

    private function actor(string ...$permissions): User
    {
        $project = Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(6).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => true,
        ]);
        $actor = User::factory()->create([
            'project_id' => $project->id,
            'sitekey' => $project->sitekey,
            'host' => $project->host,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $actor->givePermissionTo($permission);
        }

        return $actor;
    }
}
