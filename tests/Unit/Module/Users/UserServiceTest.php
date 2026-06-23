<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Module\Projects\CurrentProject;
use Module\Projects\Models\Project;
use Module\Users\DTO\UserData;
use Module\Users\Services\UserService;
use Tests\TestCase;

final class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = $this->makeProject();
        $this->app->instance(CurrentProject::class, new CurrentProject($this->project));
        $this->service = app(UserService::class);
    }

    /**
     * Создание пользователя сохраняет базовые атрибуты в БД.
     */
    public function test_create_persists_user_attributes(): void
    {
        $user = $this->service->create(new UserData(
            name: 'Иван Иванов',
            fio: null,
            email: 'ivan@example.com',
            login: null,
            externalId: null,
            password: 'secret1234',
        ));

        $this->assertNotNull($user->id);
        $this->assertDatabaseHas('users', ['email' => 'ivan@example.com', 'name' => 'Иван Иванов']);
    }

    /**
     * Создание пользователя добавляет его в project_users.
     */
    public function test_create_adds_user_to_project(): void
    {
        $user = $this->service->create(new UserData(
            name: 'Проектный',
            fio: null,
            email: 'proj-' . Str::random(6) . '@example.com',
            login: null,
            externalId: null,
            password: 'secret1234',
        ));

        $this->assertDatabaseHas('project_users', [
            'user_id'    => $user->id,
            'project_id' => $this->project->id,
        ]);
    }

    /**
     * Создание без проекта — project_users не заполняется.
     */
    public function test_create_does_not_add_to_project_when_no_current_project(): void
    {
        $this->app->instance(CurrentProject::class, new CurrentProject(null));
        $service = app(UserService::class);

        $user = $service->create(new UserData(
            name: 'Без проекта',
            fio: null,
            email: 'noproject-' . Str::random(6) . '@example.com',
            login: null,
            externalId: null,
            password: 'secret1234',
        ));

        $count = DB::table('project_users')->where('user_id', $user->id)->count();
        $this->assertSame(0, $count);
    }

    /**
     * Создание с ролью — роль синхронизируется.
     */
    public function test_create_syncs_roles(): void
    {
        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);

        $user = $this->service->create(new UserData(
            name: 'Администратор',
            fio: null,
            email: 'admin-' . Str::random(6) . '@example.com',
            login: null,
            externalId: null,
            password: 'secret1234',
            roles: ['admin'],
        ));

        $this->assertTrue($user->hasRole('admin'));
    }

    /**
     * Пароль хэшируется при создании.
     */
    public function test_create_hashes_password(): void
    {
        $plainPassword = 'plaintext123';

        $user = $this->service->create(new UserData(
            name: 'User',
            fio: null,
            email: 'hashed-' . Str::random(6) . '@example.com',
            login: null,
            externalId: null,
            password: $plainPassword,
        ));

        $stored = User::query()->find($user->id);
        $this->assertTrue(Hash::check($plainPassword, $stored->password));
        $this->assertNotSame($plainPassword, $stored->password);
    }

    /**
     * Обновление меняет имя в БД.
     */
    public function test_update_persists_new_name(): void
    {
        $user = User::factory()->create(['name' => 'Старое имя']);

        $this->service->update(new UserData(
            name: 'Новое имя',
            fio: null,
            email: $user->email,
            login: null,
            externalId: null,
            password: null,
        ), $user);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Новое имя']);
    }

    /**
     * Обновление с новым паролем — пароль хэшируется и меняется.
     */
    public function test_update_hashes_new_password_when_provided(): void
    {
        $user         = User::factory()->create();
        $newPassword  = 'new-secure-pass';

        $this->service->update(new UserData(
            name: $user->name,
            fio: null,
            email: $user->email,
            login: null,
            externalId: null,
            password: $newPassword,
        ), $user);

        $stored = User::query()->find($user->id);
        $this->assertTrue(Hash::check($newPassword, $stored->password));
    }

    /**
     * Обновление с password=null — пароль не меняется.
     */
    public function test_update_does_not_change_password_when_null(): void
    {
        $user           = User::factory()->create();
        $originalHash   = $user->password;

        $this->service->update(new UserData(
            name: $user->name,
            fio: null,
            email: $user->email,
            login: null,
            externalId: null,
            password: null,
        ), $user);

        $stored = User::query()->find($user->id);
        $this->assertSame($originalHash, $stored->password);
    }

    /**
     * Удаление пользователя — запись исчезает из БД.
     */
    public function test_delete_removes_user_from_db(): void
    {
        $user = User::factory()->create();

        $this->service->delete($user);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * find загружает отношения roles и groups.
     */
    public function test_find_loads_roles_and_groups_relations(): void
    {
        $user = User::factory()->create();

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
            'name'          => 'Project',
            'sitekey'       => 'sk-' . Str::random(6),
            'host'          => Str::random(4) . '.local',
            'shared_secret' => Str::random(32),
            'is_active'     => true,
        ]);
    }
}
