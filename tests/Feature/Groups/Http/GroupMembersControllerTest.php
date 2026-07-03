<?php

declare(strict_types=1);

namespace Tests\Feature\Groups\Http;

use Spatie\Permission\PermissionRegistrar;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Groups\Models\UserGroup;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HTTP-тесты управления участниками групп.
 * Роуты: GET|POST /api/groups/{group}/members
 *        DELETE /api/groups/{group}/members/{user}
 */
final class GroupMembersControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------
    // GET /api/groups/{group}/members
    // -------------------------------------------------------------------------

    /**
     * Список участников группы возвращается в data.
     */
    public function test_index_returns_group_members(): void
    {
        [$actor, $project] = $this->makeUserWithProject('group_view');
        $group = $this->makeGroup($project);
        $member1 = $this->makeMember($project);
        $member2 = $this->makeMember($project);
        $group->members()->syncWithoutDetaching([$member1->id, $member2->id]);

        $this->actingAs($actor)
            ->getJson("/api/groups/{$group->id}/members")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Без участников — data пуст.
     */
    public function test_index_returns_empty_data_for_group_without_members(): void
    {
        [$actor, $project] = $this->makeUserWithProject('group_view');
        $group = $this->makeGroup($project);

        $this->actingAs($actor)
            ->getJson("/api/groups/{$group->id}/members")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_candidates_returns_only_non_members_from_current_project(): void
    {
        [$actor, $project] = $this->makeUserWithProject('group_create');
        $group = $this->makeGroup($project);
        $member = $this->makeMember($project);
        $candidate = $this->makeMember($project);
        $otherProjectUser = $this->makeMember($this->makeProject());
        $group->members()->attach($member);

        $response = $this->actingAs($actor)
            ->getJson("/api/groups/{$group->id}/member-candidates?filter[search]=".urlencode($candidate->email))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $candidate->id);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains((string) $member->id, $ids);
        $this->assertNotContains((string) $otherProjectUser->id, $ids);
    }

    /**
     * Без пермишена group_view — 403.
     */
    public function test_index_returns_403_without_permission(): void
    {
        [$actor, $project] = $this->makeUserWithProject();
        $group = $this->makeGroup($project);

        $this->actingAs($actor)
            ->getJson("/api/groups/{$group->id}/members")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // POST /api/groups/{group}/members
    // -------------------------------------------------------------------------

    /**
     * Добавление пользователя в группу — 204, участник появляется в group.
     */
    public function test_store_adds_member_to_group(): void
    {
        [$actor, $project] = $this->makeUserWithProject('group_create');
        $group = $this->makeGroup($project);
        $member = $this->makeMember($project);

        $this->actingAs($actor)
            ->postJson("/api/groups/{$group->id}/members", ['user_id' => $member->id])
            ->assertNoContent();

        $this->assertTrue($group->members()->where('users.id', $member->id)->exists());
    }

    /**
     * Несуществующий user_id — 422.
     */
    public function test_store_returns_422_for_nonexistent_user(): void
    {
        [$actor, $project] = $this->makeUserWithProject('group_create');
        $group = $this->makeGroup($project);

        $this->actingAs($actor)
            ->postJson("/api/groups/{$group->id}/members", ['user_id' => 999999])
            ->assertUnprocessable();
    }

    public function test_store_rejects_member_from_other_project(): void
    {
        [$actor, $project] = $this->makeUserWithProject('group_create');
        $group = $this->makeGroup($project);
        $member = $this->makeMember($this->makeProject());

        $this->actingAs($actor)
            ->postJson("/api/groups/{$group->id}/members", ['user_id' => $member->id])
            ->assertUnprocessable();

        $this->assertFalse($group->members()->where('users.id', $member->id)->exists());
    }

    /**
     * Без пермишена group_create — 403.
     */
    public function test_store_returns_403_without_permission(): void
    {
        [$actor, $project] = $this->makeUserWithProject('group_view');
        $group = $this->makeGroup($project);
        $member = $this->makeMember($project);

        $this->actingAs($actor)
            ->postJson("/api/groups/{$group->id}/members", ['user_id' => $member->id])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // DELETE /api/groups/{group}/members/{user}
    // -------------------------------------------------------------------------

    /**
     * Удаление участника из группы — 204, участник больше не в группе.
     */
    public function test_destroy_removes_member_from_group(): void
    {
        [$actor, $project] = $this->makeUserWithProject('group_create');
        $group = $this->makeGroup($project);
        $member = $this->makeMember($project);
        $group->members()->syncWithoutDetaching([$member->id]);

        $this->actingAs($actor)
            ->deleteJson("/api/groups/{$group->id}/members/{$member->id}")
            ->assertNoContent();

        $this->assertFalse($group->members()->where('users.id', $member->id)->exists());
    }

    /**
     * Без пермишена group_create — 403.
     */
    public function test_destroy_returns_403_without_permission(): void
    {
        [$actor, $project] = $this->makeUserWithProject('group_view');
        $group = $this->makeGroup($project);
        $member = $this->makeMember($project);
        $group->members()->syncWithoutDetaching([$member->id]);

        $this->actingAs($actor)
            ->deleteJson("/api/groups/{$group->id}/members/{$member->id}")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array{User, Project} */
    private function makeUserWithProject(string ...$permissions): array
    {
        $project = $this->makeProject();
        $user = User::factory()->create([
            'sitekey' => $project->sitekey,
            'host' => $project->host,
            'project_id' => $project->id,
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

    private function makeGroup(Project $project): UserGroup
    {
        return UserGroup::query()->create([
            'name' => 'Group '.Str::random(4),
            'slug' => 'group-'.Str::random(6),
            'site_id' => $project->id,
            'is_active' => true,
        ]);
    }

    private function makeMember(Project $project): User
    {
        return User::factory()->create(['project_id' => $project->id]);
    }
}
