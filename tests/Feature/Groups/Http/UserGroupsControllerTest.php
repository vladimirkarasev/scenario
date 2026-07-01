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
 * HTTP-тесты CRUD групп пользователей.
 * Роуты: GET|POST /api/groups, GET|PUT|DELETE /api/groups/{group}
 */
final class UserGroupsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------
    // GET /api/groups
    // -------------------------------------------------------------------------

    /**
     * Список возвращает группы текущего проекта.
     */
    public function test_index_returns_groups_for_current_project(): void
    {
        [$user, $project] = $this->makeUserWithProject('group_view');
        $this->makeGroup($project);
        $this->makeGroup($project);

        $this->actingAs($user)
            ->getJson('/api/groups')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Группы другого проекта не попадают в ответ.
     */
    public function test_index_excludes_groups_from_other_project(): void
    {
        [$user] = $this->makeUserWithProject('group_view');
        $other = $this->makeProject();
        $this->makeGroup($other);

        $this->actingAs($user)
            ->getJson('/api/groups')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Без пермишена group_view — 403.
     */
    public function test_index_returns_403_without_permission(): void
    {
        [$user] = $this->makeUserWithProject();

        $this->actingAs($user)
            ->getJson('/api/groups')
            ->assertForbidden();
    }

    /**
     * Без авторизации — 401.
     */
    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/groups')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // GET /api/groups/{group}
    // -------------------------------------------------------------------------

    /**
     * Существующая группа — 200 с name и slug в attributes.
     */
    public function test_show_returns_group_attributes(): void
    {
        [$user, $project] = $this->makeUserWithProject('group_view');
        $group = $this->makeGroup($project, name: 'Администраторы', slug: 'admins');

        $this->actingAs($user)
            ->getJson("/api/groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Администраторы')
            ->assertJsonPath('data.attributes.slug', 'admins');
    }

    /**
     * Несуществующий UUID — 404.
     */
    public function test_show_returns_404_for_nonexistent(): void
    {
        [$user] = $this->makeUserWithProject('group_view');

        $this->actingAs($user)
            ->getJson('/api/groups/'.Str::uuid())
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // POST /api/groups
    // -------------------------------------------------------------------------

    /**
     * Создание группы — 201, запись появляется в БД с site_id проекта.
     */
    public function test_store_creates_group_and_returns_201(): void
    {
        [$user, $project] = $this->makeUserWithProject('group_create');

        $this->actingAs($user)
            ->postJson('/api/groups', [
                'name' => 'Новая группа',
                'slug' => 'new-group',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.name', 'Новая группа');

        $this->assertDatabaseHas('user_groups', [
            'name' => 'Новая группа',
            'slug' => 'new-group',
            'site_id' => $project->id,
        ]);
    }

    /**
     * Пустое тело — 422.
     */
    public function test_store_returns_422_when_required_fields_missing(): void
    {
        [$user] = $this->makeUserWithProject('group_create');

        $this->actingAs($user)
            ->postJson('/api/groups', [])
            ->assertUnprocessable();
    }

    /**
     * Дублирующийся slug — 422.
     */
    public function test_store_returns_422_when_slug_not_unique(): void
    {
        [$user, $project] = $this->makeUserWithProject('group_create');
        $this->makeGroup($project, slug: 'duplicate-slug');

        $this->actingAs($user)
            ->postJson('/api/groups', [
                'name' => 'Другая группа',
                'slug' => 'duplicate-slug',
            ])
            ->assertUnprocessable();
    }

    /**
     * Без пермишена group_create — 403.
     */
    public function test_store_returns_403_without_permission(): void
    {
        [$user] = $this->makeUserWithProject('group_view');

        $this->actingAs($user)
            ->postJson('/api/groups', ['name' => 'Test', 'slug' => 'test'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // PUT /api/groups/{group}
    // -------------------------------------------------------------------------

    /**
     * Обновление name сохраняется в БД.
     */
    public function test_update_persists_new_name(): void
    {
        [$user, $project] = $this->makeUserWithProject('group_create');
        $group = $this->makeGroup($project, name: 'Старое имя', slug: 'old-slug');

        $this->actingAs($user)
            ->putJson("/api/groups/{$group->id}", [
                'name' => 'Новое имя',
                'slug' => 'new-slug',
            ])
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Новое имя');

        $this->assertDatabaseHas('user_groups', ['id' => $group->id, 'name' => 'Новое имя']);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/groups/{group}
    // -------------------------------------------------------------------------

    /**
     * Удаление группы — 204, запись исчезает из БД.
     */
    public function test_destroy_deletes_group_and_returns_204(): void
    {
        [$user, $project] = $this->makeUserWithProject('group_delete');
        $group = $this->makeGroup($project);

        $this->actingAs($user)
            ->deleteJson("/api/groups/{$group->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('user_groups', ['id' => $group->id]);
    }

    /**
     * Без пермишена group_delete — 403.
     */
    public function test_destroy_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject('group_view');
        $group = $this->makeGroup($project);

        $this->actingAs($user)
            ->deleteJson("/api/groups/{$group->id}")
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

    private function makeGroup(Project $project, string $name = 'Test Group', string $slug = ''): UserGroup
    {
        return UserGroup::query()->create([
            'name' => $name,
            'slug' => $slug ?: 'group-'.Str::random(6),
            'site_id' => $project->id,
            'is_active' => true,
        ]);
    }
}
