<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Users;

use Module\Users\Models\Role;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Module\Projects\CurrentProject;
use Module\Projects\Models\Project;
use Module\Groups\Models\UserGroup;
use Module\Users\DTO\UserData;
use Module\Users\Services\UserService;
use App\Exceptions\ConflictException;
use Tests\TestCase;

final class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;
    private Project $project;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = $this->makeProject();
        $this->app->instance(CurrentProject::class, new CurrentProject($this->project));
        $this->service = app(UserService::class);
        $administrator = Role::query()->create([
            'name' => 'administrator',
            'guard_name' => 'web',
            'is_system' => true,
        ]);
        $this->actor = $this->makeUserInProject();
        $this->actor->assignRole($administrator);
    }

    /**
     * Создание пользователя сохраняет базовые атрибуты в БД.
     */
    public function test_create_persists_user_attributes(): void
    {
        $user = $this->service->create(
            $this->actor,
            new UserData(
                name: 'Иван Иванов',
                fio: null,
                email: 'ivan@example.com',
                login: 'login-'.Str::random(8),
                externalId: null,
                password: 'secret1234',
            )
        );

        $this->assertNotNull($user->id);
        $this->assertDatabaseHas('users', ['email' => 'ivan@example.com', 'name' => 'Иван Иванов']);
    }

    /**
     * Создание пользователя привязывает его к текущему проекту.
     */
    public function test_create_adds_user_to_project(): void
    {
        $user = $this->service->create(
            $this->actor,
            new UserData(
                name: 'Проектный',
                fio: null,
                email: 'proj-'.Str::random(6).'@example.com',
                login: 'login-'.Str::random(8),
                externalId: null,
                password: 'secret1234',
            )
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'project_id' => $this->project->id,
        ]);
    }

    public function test_create_fails_without_current_project(): void
    {
        $this->app->instance(CurrentProject::class, new CurrentProject(null));
        $service = app(UserService::class);

        $this->expectException(\LogicException::class);

        $service->create(
            $this->actor,
            new UserData(
                name: 'Без проекта',
                fio: null,
                email: 'noproject-'.Str::random(6).'@example.com',
                login: 'login-'.Str::random(8),
                externalId: null,
                password: 'secret1234',
            )
        );
    }

    /**
     * Создание с ролью — роль синхронизируется.
     */
    public function test_create_syncs_roles(): void
    {
        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);

        $user = $this->service->create(
            $this->actor,
            new UserData(
                name: 'Администратор',
                fio: null,
                email: 'admin-'.Str::random(6).'@example.com',
                login: 'login-'.Str::random(8),
                externalId: null,
                password: 'secret1234',
                roles: ['admin'],
                rolesProvided: true,
            )
        );

        $this->assertTrue($user->hasRole('admin'));
    }

    /**
     * Пароль хэшируется при создании.
     */
    public function test_create_hashes_password(): void
    {
        $plainPassword = 'plaintext123';

        $user = $this->service->create(
            $this->actor,
            new UserData(
                name: 'User',
                fio: null,
                email: 'hashed-'.Str::random(6).'@example.com',
                login: 'login-'.Str::random(8),
                externalId: null,
                password: $plainPassword,
            )
        );

        $stored = User::query()->find($user->id);
        $this->assertTrue(Hash::check($plainPassword, $stored->password));
        $this->assertNotSame($plainPassword, $stored->password);
    }

    public function test_create_rolls_back_when_role_sync_fails(): void
    {
        $email = 'rollback-'.Str::random(6).'@example.com';

        try {
            $this->service->create(
                $this->actor,
                new UserData(
                    name: 'Rollback',
                    fio: null,
                    email: $email,
                    login: 'login-'.Str::random(8),
                    externalId: null,
                    password: 'secret1234',
                    roles: ['missing-role'],
                    rolesProvided: true,
                )
            );
            self::fail('Expected missing role exception.');
        } catch (ConflictException $e) {
            $this->assertSame('ROLE_NOT_FOUND', $e->errorCode);
            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    /**
     * Обновление меняет имя в БД.
     */
    public function test_update_persists_new_name(): void
    {
        $user = $this->makeUserInProject(['name' => 'Старое имя']);

        $this->service->update(
            $this->actor,
            new UserData(
                name: 'Новое имя',
                fio: null,
                email: $user->email,
                login: 'login-'.Str::random(8),
                externalId: null,
                password: null,
            ),
            $user,
        );

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Новое имя']);
    }

    /**
     * Обновление с новым паролем — пароль хэшируется и меняется.
     */
    public function test_update_hashes_new_password_when_provided(): void
    {
        $user = $this->makeUserInProject();
        $newPassword = 'new-secure-pass';

        $this->service->update(
            $this->actor,
            new UserData(
                name: $user->name,
                fio: null,
                email: $user->email,
                login: 'login-'.Str::random(8),
                externalId: null,
                password: $newPassword,
            ),
            $user,
        );

        $stored = User::query()->find($user->id);
        $this->assertTrue(Hash::check($newPassword, $stored->password));
    }

    /**
     * Обновление с password=null — пароль не меняется.
     */
    public function test_update_does_not_change_password_when_null(): void
    {
        $user = $this->makeUserInProject();
        $originalHash = $user->password;

        $this->service->update(
            $this->actor,
            new UserData(
                name: $user->name,
                fio: null,
                email: $user->email,
                login: 'login-'.Str::random(8),
                externalId: null,
                password: null,
            ),
            $user,
        );

        $stored = User::query()->find($user->id);
        $this->assertSame($originalHash, $stored->password);
    }

    public function test_update_removes_groups_from_other_projects(): void
    {
        $user = $this->makeUserInProject();
        $otherProject = $this->makeProject();
        $currentGroup = UserGroup::query()->create([
            'name' => 'Current',
            'slug' => 'current',
            'site_id' => $this->project->id,
            'is_active' => true,
        ]);
        $otherGroup = UserGroup::query()->create([
            'name' => 'Other',
            'slug' => 'other',
            'site_id' => $otherProject->id,
            'is_active' => true,
        ]);
        $user->groups()->sync([$currentGroup->id, $otherGroup->id]);

        $this->service->update(
            $this->actor,
            new UserData(
                name: $user->name,
                fio: null,
                email: $user->email,
                login: 'login-'.Str::random(8),
                externalId: null,
                password: null,
                groupIds: [],
                groupIdsProvided: true,
            ),
            $user,
        );

        $this->assertDatabaseMissing('user_group_members', [
            'user_id' => $user->id,
            'user_group_id' => $currentGroup->id,
        ]);
        $this->assertDatabaseMissing('user_group_members', [
            'user_id' => $user->id,
            'user_group_id' => $otherGroup->id,
        ]);
    }

    /**
     * Удаление пользователя — запись исчезает из БД.
     */
    public function test_delete_removes_user_from_db(): void
    {
        $user = $this->makeUserInProject();

        $this->service->delete($this->actor, $user);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * find загружает отношения roles и groups.
     */
    public function test_find_loads_roles_and_groups_relations(): void
    {
        $user = $this->makeUserInProject();

        $result = $this->service->find($user);

        $this->assertTrue($result->relationLoaded('roles'));
        $this->assertTrue($result->relationLoaded('groups'));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeProject(): Project
    {
        return Project::query()->create([
            'name' => 'Project',
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(4).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function makeUserInProject(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'login' => 'login-'.Str::random(8),
        ], $attributes));
    }
}
