<?php

declare(strict_types=1);

namespace Tests\Feature\Users\Http;

use Spatie\Permission\PermissionRegistrar;
use Module\Users\Models\Role;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Groups\Models\UserGroup;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HTTP-тесты CRUD пользователей.
 * Роуты: GET|POST /api/users, GET|PUT|DELETE /api/users/{user}
 */
final class UsersControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------
    // GET /api/users
    // -------------------------------------------------------------------------

    /**
     * Список возвращает пользователей текущего проекта.
     */
    public function test_index_returns_users_in_project(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_view');
        $this->makeUserInProject($project);
        $this->makeUserInProject($project);

        $this->actingAs($actor)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /**
     * Пользователи другого проекта не попадают в ответ.
     */
    public function test_index_excludes_users_from_other_project(): void
    {
        [$actor] = $this->makeUserWithProject('user_view');
        $other = $this->makeProject();
        $this->makeUserInProject($other);

        $this->actingAs($actor)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_external_id_is_unique_per_project_not_globally(): void
    {
        $firstProject = $this->makeProject();
        $secondProject = $this->makeProject();

        $first = $this->makeUserInProject($firstProject, ['external_id' => 'shared-id']);
        $second = $this->makeUserInProject($secondProject, ['external_id' => 'shared-id']);

        $this->assertNotSame($first->id, $second->id);
    }

    /**
     * Без пермишена user_view — 403.
     */
    public function test_index_returns_403_without_permission(): void
    {
        [$actor] = $this->makeUserWithProject();

        $this->actingAs($actor)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    /**
     * Без авторизации — 401.
     */
    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // GET /api/users/{user}
    // -------------------------------------------------------------------------

    /**
     * Существующий пользователь — 200 с name и email в attributes.
     */
    public function test_show_returns_user_attributes(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_view');
        $user = $this->makeUserInProject($project, ['name' => 'Иван Иванов']);

        $this->actingAs($actor)
            ->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Иван Иванов');
    }

    /**
     * Несуществующий ID — 404.
     */
    public function test_show_returns_404_for_nonexistent(): void
    {
        [$actor] = $this->makeUserWithProject('user_view');

        $this->actingAs($actor)
            ->getJson('/api/users/999999')
            ->assertNotFound();
    }

    /**
     * ?include=roles,groups — связи отдаются linkage + в top-level included,
     * а sparse fieldsets ограничивают набор полей групп/ролей.
     */
    public function test_show_includes_relations_with_sparse_fields(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_view');

        $user = User::factory()->create(['name' => 'С группой']);
        $this->attachUserToProject($user, $project);

        $group = UserGroup::query()->create([
            'name' => 'Операторы',
            'slug' => 'operators',
            'site_id' => $project->id,
            'is_active' => true,
        ]);
        $user->groups()->sync([$group->id]);

        $role = Role::query()->create(['name' => 'manager', 'title' => 'Менеджер', 'guard_name' => 'web']);
        $user->assignRole($role);

        $response = $this->actingAs($actor)
            ->getJson("/api/users/{$user->id}?include=roles,groups&fields[groups]=name,slug&fields[roles]=name,title")
            ->assertOk()
            ->assertJsonPath('data.relationships.groups.data.0.id', (string) $group->id)
            ->assertJsonPath('data.relationships.roles.data.0.id', (string) $role->id);

        $included = collect($response->json('included'));

        $includedGroup = $included->firstWhere('type', 'groups');
        $this->assertSame('Операторы', $includedGroup['attributes']['name']);
        $this->assertSame('operators', $includedGroup['attributes']['slug']);
        $this->assertSame(['name', 'slug'], array_keys($includedGroup['attributes']));

        $includedRole = $included->firstWhere('type', 'roles');
        $this->assertSame('manager', $includedRole['attributes']['name']);
        $this->assertSame(['name', 'title'], array_keys($includedRole['attributes']));
    }

    // -------------------------------------------------------------------------
    // POST /api/users
    // -------------------------------------------------------------------------

    /**
     * Создание пользователя — 201, запись появляется в БД и добавляется в проект.
     */
    public function test_store_creates_user_and_returns_201(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_create');
        $email = 'user-'.Str::random(8).'@example.com';

        $this->actingAs($actor)
            ->postJson('/api/users', [
                'name' => 'Новый пользователь',
                'email' => $email,
                'login' => 'login-'.Str::random(8),
                'password' => 'secret1234',
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.name', 'Новый пользователь');

        $this->assertDatabaseHas('users', ['name' => 'Новый пользователь', 'email' => $email]);
    }

    /**
     * Пустое тело — 422.
     */
    public function test_store_returns_422_when_required_fields_missing(): void
    {
        [$actor] = $this->makeUserWithProject('user_create');

        $this->actingAs($actor)
            ->postJson('/api/users', [])
            ->assertUnprocessable();
    }

    /**
     * Дублирующийся email — 422.
     */
    public function test_store_returns_422_when_email_already_exists(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_create');
        $existing = $this->makeUserInProject($project);

        $this->actingAs($actor)
            ->postJson('/api/users', [
                'name' => 'Другой',
                'email' => $existing->email,
                'login' => 'login-'.Str::random(8),
                'password' => 'secret1234',
            ])
            ->assertUnprocessable();
    }

    /**
     * Без пермишена user_create — 403.
     */
    public function test_store_returns_403_without_permission(): void
    {
        [$actor] = $this->makeUserWithProject('user_view');

        $this->actingAs($actor)
            ->postJson('/api/users', [
                'name' => 'Test',
                'email' => 'test@example.com',
                'password' => 'secret1234',
            ])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // PUT /api/users/{user}
    // -------------------------------------------------------------------------

    /**
     * Обновление name сохраняется в БД.
     */
    public function test_update_persists_new_name(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_update');
        $user = $this->makeUserInProject($project, ['name' => 'Старое имя']);

        $this->actingAs($actor)
            ->putJson("/api/users/{$user->id}", [
                'name' => 'Новое имя',
                'email' => $user->email,
                'login' => 'login-'.Str::random(8),
            ])
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Новое имя');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Новое имя']);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/users/{user}
    // -------------------------------------------------------------------------

    /**
     * Удаление пользователя — 204, запись исчезает из БД.
     */
    public function test_destroy_deletes_user_and_returns_204(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_delete');
        $user = $this->makeUserInProject($project);
        $token = $user->createToken('orphan-check')->accessToken;

        $this->actingAs($actor)
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    /**
     * Без пермишена user_delete — 403.
     */
    public function test_destroy_returns_403_without_permission(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_view');
        $user = $this->makeUserInProject($project);

        $this->actingAs($actor)
            ->deleteJson("/api/users/{$user->id}")
            ->assertForbidden();
    }

    public function test_show_returns_404_for_user_from_other_project(): void
    {
        [$actor] = $this->makeUserWithProject('user_view');
        $otherProject = $this->makeProject();
        $target = $this->makeUserInProject($otherProject);

        $this->actingAs($actor)
            ->getJson("/api/users/{$target->id}")
            ->assertNotFound();
    }

    public function test_update_returns_404_for_user_from_other_project(): void
    {
        [$actor] = $this->makeUserWithProject('user_update');
        $otherProject = $this->makeProject();
        $target = $this->makeUserInProject($otherProject);

        $this->actingAs($actor)
            ->putJson("/api/users/{$target->id}", [
                'name' => 'Недоступный',
                'email' => $target->email,
            ])
            ->assertNotFound();
    }

    public function test_patch_is_not_exposed_as_full_update(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_update');
        $target = $this->makeUserInProject($project);

        $this->actingAs($actor)
            ->patchJson("/api/users/{$target->id}", ['name' => 'Partial'])
            ->assertMethodNotAllowed();
    }

    public function test_index_returns_403_without_current_project(): void
    {
        $actor = User::factory()->create(['sitekey' => null, 'host' => null]);
        Permission::firstOrCreate(['name' => 'user_view', 'guard_name' => 'web']);
        $actor->givePermissionTo('user_view');

        $this->actingAs($actor)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_index_rejects_unbounded_page_size(): void
    {
        [$actor] = $this->makeUserWithProject('user_view');

        $this->actingAs($actor)
            ->getJson('/api/users?per_page=101')
            ->assertUnprocessable();
    }

    public function test_store_rejects_group_from_other_project(): void
    {
        [$actor] = $this->makeUserWithProject('user_create');
        $otherProject = $this->makeProject();
        $foreignGroup = UserGroup::query()->create([
            'name' => 'Чужая группа',
            'slug' => 'foreign-group',
            'site_id' => $otherProject->id,
            'is_active' => true,
        ]);

        $this->actingAs($actor)
            ->postJson('/api/users', [
                'name' => 'Новый пользователь',
                'email' => 'foreign-group@example.test',
                'password' => 'secret1234',
                'group_ids' => [$foreignGroup->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('group_ids.0');
    }

    public function test_destroy_rejects_current_user(): void
    {
        [$actor] = $this->makeUserWithProject('user_delete');

        $this->actingAs($actor)
            ->deleteJson("/api/users/{$actor->id}")
            ->assertUnprocessable();
    }

    public function test_destroy_rejects_administrator_for_less_privileged_actor(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_delete');
        $administrator = Role::query()->create([
            'name' => 'administrator',
            'guard_name' => 'web',
            'is_system' => true,
        ]);
        $target = $this->makeUserInProject($project);
        $target->assignRole($administrator);

        $this->actingAs($actor)
            ->deleteJson("/api/users/{$target->id}")
            ->assertForbidden();
    }

    public function test_create_permission_cannot_assign_administrator_role(): void
    {
        [$actor] = $this->makeUserWithProject('user_create');
        Role::query()->create([
            'name' => 'administrator',
            'guard_name' => 'web',
            'is_system' => true,
        ]);

        $this->actingAs($actor)
            ->postJson('/api/users', [
                'name' => 'Escalated',
                'email' => 'escalated@example.test',
                'login' => 'escalated',
                'password' => 'secret1234',
                'roles' => ['administrator'],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'escalated@example.test']);
    }

    public function test_update_permission_cannot_assign_roles(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_update');
        $target = $this->makeUserInProject($project, ['login' => 'target']);
        Role::query()->create(['name' => 'operator', 'guard_name' => 'web']);

        $this->actingAs($actor)
            ->putJson("/api/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'login' => $target->login,
                'roles' => ['operator'],
            ])
            ->assertForbidden();

        $this->assertFalse($target->fresh()->hasRole('operator'));
    }

    public function test_update_cannot_remove_last_administrator(): void
    {
        $project = $this->makeProject();
        $administrator = Role::query()->create([
            'name' => 'administrator',
            'guard_name' => 'web',
            'is_system' => true,
        ]);
        $actor = $this->makeUserInProject($project, ['login' => 'last-admin']);
        $actor->assignRole($administrator);

        $this->actingAs($actor)
            ->putJson("/api/users/{$actor->id}", [
                'name' => $actor->name,
                'email' => $actor->email,
                'login' => $actor->login,
                'roles' => [],
            ])
            ->assertUnprocessable();

        $this->assertTrue($actor->fresh()->hasRole('administrator'));
    }

    public function test_system_user_cannot_be_updated_or_deleted(): void
    {
        $project = $this->makeProject();
        $administrator = Role::query()->create([
            'name' => 'administrator',
            'guard_name' => 'web',
            'is_system' => true,
        ]);
        $actor = $this->makeUserInProject($project, ['login' => 'admin']);
        $actor->assignRole($administrator);
        $system = $this->makeUserInProject($project, [
            'login' => 'system',
            'is_system' => true,
        ]);

        $this->actingAs($actor)
            ->putJson("/api/users/{$system->id}", [
                'name' => 'Changed',
                'email' => $system->email,
                'login' => $system->login,
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->deleteJson("/api/users/{$system->id}")
            ->assertForbidden();
    }

    public function test_update_preserves_groups_when_group_ids_are_absent(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_update');
        $target = $this->makeUserInProject($project, ['login' => 'grouped-user']);
        $group = UserGroup::query()->create([
            'name' => 'Operators',
            'slug' => 'operators',
            'site_id' => $project->id,
            'is_active' => true,
        ]);
        $target->groups()->attach($group);

        $this->actingAs($actor)
            ->putJson("/api/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'login' => $target->login,
            ])
            ->assertOk();

        $this->assertDatabaseHas('user_group_members', [
            'user_id' => $target->id,
            'user_group_id' => $group->id,
        ]);
    }

    public function test_show_includes_complete_role_attributes(): void
    {
        [$actor, $project] = $this->makeUserWithProject('user_view');
        $target = $this->makeUserInProject($project);
        $permission = Permission::firstOrCreate([
            'name' => 'user_update',
            'guard_name' => 'web',
        ]);
        $role = Role::query()->create([
            'name' => 'complete-role',
            'guard_name' => 'web',
            'title' => 'Complete role',
            'description' => 'Full role description',
            'is_system' => false,
        ]);
        $role->syncPermissions([$permission]);
        $target->assignRole($role);

        $response = $this->actingAs($actor)
            ->getJson("/api/users/{$target->id}?include=roles")
            ->assertOk();

        $included = collect($response->json('included'))->firstWhere('type', 'roles');
        $this->assertSame('Full role description', data_get($included, 'attributes.description'));
        $this->assertSame(['user_update'], data_get($included, 'attributes.permissions'));
        $this->assertSame('web', data_get($included, 'attributes.guard_name'));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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

    /** @param  array<string, mixed>  $attributes */
    private function makeUserInProject(Project $project, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->attachUserToProject($user, $project);

        return $user;
    }

    private function attachUserToProject(User $user, Project $project): void
    {
        $user->update(['project_id' => $project->id]);
    }
}
