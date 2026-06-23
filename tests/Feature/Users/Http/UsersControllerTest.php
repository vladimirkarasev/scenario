<?php

declare(strict_types=1);

namespace Tests\Feature\Users\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
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
            ->assertJsonCount(2, 'data');
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
            ->assertJsonCount(0, 'data');
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
        [$actor] = $this->makeUserWithProject('user_view');
        $user = User::factory()->create(['name' => 'Иван Иванов']);

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
        [$actor] = $this->makeUserWithProject('user_create');
        $existing = User::factory()->create();

        $this->actingAs($actor)
            ->postJson('/api/users', [
                'name' => 'Другой',
                'email' => $existing->email,
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
        [$actor] = $this->makeUserWithProject('user_create');
        $user = User::factory()->create(['name' => 'Старое имя']);

        $this->actingAs($actor)
            ->putJson("/api/users/{$user->id}", [
                'name' => 'Новое имя',
                'email' => $user->email,
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
        [$actor] = $this->makeUserWithProject('user_delete');
        $user = User::factory()->create();

        $this->actingAs($actor)
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * Без пермишена user_delete — 403.
     */
    public function test_destroy_returns_403_without_permission(): void
    {
        [$actor] = $this->makeUserWithProject('user_view');
        $user = User::factory()->create();

        $this->actingAs($actor)
            ->deleteJson("/api/users/{$user->id}")
            ->assertForbidden();
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

        DB::table('project_users')->insert([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
