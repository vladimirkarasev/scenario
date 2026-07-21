<?php

declare(strict_types=1);

namespace Tests\Feature\Users\Http;

use Spatie\Permission\PermissionRegistrar;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class UserTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_index_returns_user_tokens(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_token_view');
        $target = $this->makeUserInProject($project);
        $target->createToken('Token Alpha');
        $target->createToken('Token Beta');

        $this->actingAs($actor)
            ->getJson("/api/users/{$target->id}/tokens")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_returns_empty_data_when_no_tokens(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_token_view');
        $target = $this->makeUserInProject($project);

        $this->actingAs($actor)
            ->getJson("/api/users/{$target->id}/tokens")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_returns_403_without_permission(): void
    {
        [$actor, $project] = $this->makeUserWithProject();
        $target = $this->makeUserInProject($project);

        $this->actingAs($actor)
            ->getJson("/api/users/{$target->id}/tokens")
            ->assertForbidden();
    }

    public function test_store_creates_token_and_returns_plain_text(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_token_manage');
        $target = $this->makeUserInProject($project);

        $response = $this->actingAs($actor)
            ->postJson("/api/users/{$target->id}/tokens", ['name' => 'My Token'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'My Token');

        $this->assertNotNull($response->json('data.plain_text_token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $target->id,
            'tokenable_type' => User::class,
            'name' => 'My Token',
        ]);
    }

    public function test_store_returns_422_when_name_missing(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_token_manage');
        $target = $this->makeUserInProject($project);

        $this->actingAs($actor)
            ->postJson("/api/users/{$target->id}/tokens", [])
            ->assertUnprocessable();
    }

    public function test_store_returns_403_without_permission(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_token_view');
        $target = $this->makeUserInProject($project);

        $this->actingAs($actor)
            ->postJson("/api/users/{$target->id}/tokens", ['name' => 'Token'])
            ->assertForbidden();
    }

    public function test_destroy_deletes_token_and_returns_204(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_token_manage');
        $target = $this->makeUserInProject($project);
        $issued = $target->createToken('To Delete');
        $tokenId = $issued->accessToken->id;

        $this->actingAs($actor)
            ->deleteJson("/api/users/{$target->id}/tokens/{$tokenId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_destroy_is_idempotent_for_nonexistent_token(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_token_manage');
        $target = $this->makeUserInProject($project);

        $this->actingAs($actor)
            ->deleteJson("/api/users/{$target->id}/tokens/999999")
            ->assertNoContent();
    }

    public function test_store_returns_404_for_user_from_other_project(): void
    {
        [$actor] = $this->makeUserWithProject('user_token_manage');
        $target = $this->makeUserInProject($this->makeProject());

        $this->actingAs($actor)
            ->postJson("/api/users/{$target->id}/tokens", ['name' => 'Forbidden'])
            ->assertNotFound();
    }

    public function test_store_rejects_more_privileged_target(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_token_manage');
        $target = $this->makeUserInProject($project);
        Permission::firstOrCreate(['name' => 'project_delete', 'guard_name' => 'web']);
        $target->givePermissionTo('project_delete');

        $this->actingAs($actor)
            ->postJson("/api/users/{$target->id}/tokens", ['name' => 'Escalation'])
            ->assertForbidden();
    }

    public function test_index_rejects_more_privileged_target(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_token_view');
        $target = $this->makeUserInProject($project);
        Permission::firstOrCreate(['name' => 'project_delete', 'guard_name' => 'web']);
        $target->givePermissionTo('project_delete');

        $this->actingAs($actor)
            ->getJson("/api/users/{$target->id}/tokens")
            ->assertForbidden();
    }

    /** @return array{User, Project} */
    private function makeUserWithProject(string ...$permissions): array
    {
        $project = $this->makeProject();
        $actor = User::factory()->create([
            'sitekey' => $project->sitekey,
            'host' => $project->host,
        ]);
        $this->attachUserToProject($actor, $project);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $actor->givePermissionTo($permission);
        }

        return [$actor, $project];
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

    private function makeUserInProject(Project $project): User
    {
        $user = User::factory()->create();
        $this->attachUserToProject($user, $project);

        return $user;
    }

    private function attachUserToProject(User $user, Project $project): void
    {
        $user->update(['project_id' => $project->id]);
    }
}
