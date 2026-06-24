<?php

declare(strict_types=1);

namespace Tests\Feature\Projects\Http;

use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HTTP-тесты CRUD проектов.
 * Роуты: GET|POST /api/projects, GET|PUT|DELETE /api/projects/{project}
 */
final class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------
    // GET /api/projects
    // -------------------------------------------------------------------------

    /**
     * Список возвращает все проекты.
     */
    public function test_index_returns_projects(): void
    {
        $user = $this->makeUser('project_view');
        $this->makeProject();
        $this->makeProject();

        $this->actingAs($user)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Без пермишена project_view — 403.
     */
    public function test_index_returns_403_without_permission(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson('/api/projects')
            ->assertForbidden();
    }

    /**
     * Без авторизации — 401.
     */
    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/projects')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // GET /api/projects/{project}
    // -------------------------------------------------------------------------

    /**
     * Существующий проект — 200 с name в attributes.
     */
    public function test_show_returns_project_attributes(): void
    {
        $user = $this->makeUser('project_view');
        $project = $this->makeProject(name: 'Тестовый проект');

        $this->actingAs($user)
            ->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Тестовый проект');
    }

    /**
     * Несуществующий UUID — 404.
     */
    public function test_show_returns_404_for_nonexistent(): void
    {
        $user = $this->makeUser('project_view');

        $this->actingAs($user)
            ->getJson('/api/projects/'.Str::uuid())
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // POST /api/projects
    // -------------------------------------------------------------------------

    /**
     * Валидные данные — 201, запись появляется в БД.
     */
    public function test_store_creates_project_and_returns_201(): void
    {
        $user = $this->makeUser('project_create');

        $this->actingAs($user)
            ->postJson('/api/projects', [
                'name' => 'Новый проект',
                'sitekey' => 'sk-test001',
                'host' => 'test.local',
                'shared_secret' => Str::random(32),
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.name', 'Новый проект');

        $this->assertDatabaseHas('projects', ['name' => 'Новый проект', 'sitekey' => 'sk-test001']);
    }

    /**
     * Пустое тело — 422.
     */
    public function test_store_returns_422_when_required_fields_missing(): void
    {
        $user = $this->makeUser('project_create');

        $this->actingAs($user)
            ->postJson('/api/projects', [])
            ->assertUnprocessable();
    }

    /**
     * shared_secret меньше 32 символов — 422.
     */
    public function test_store_returns_422_when_shared_secret_too_short(): void
    {
        $user = $this->makeUser('project_create');

        $this->actingAs($user)
            ->postJson('/api/projects', [
                'name' => 'Test',
                'sitekey' => 'sk-test',
                'host' => 'test.local',
                'shared_secret' => 'short',
                'is_active' => true,
            ])
            ->assertUnprocessable();
    }

    /**
     * Без пермишена project_create — 403.
     */
    public function test_store_returns_403_without_permission(): void
    {
        $user = $this->makeUser('project_view');

        $this->actingAs($user)
            ->postJson('/api/projects', [
                'name' => 'Test',
                'sitekey' => 'sk-test',
                'host' => 'test.local',
                'shared_secret' => Str::random(32),
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // PUT /api/projects/{project}
    // -------------------------------------------------------------------------

    /**
     * Переименование проекта сохраняется в БД.
     */
    public function test_update_persists_new_name(): void
    {
        $user = $this->makeUser('project_create');
        $project = $this->makeProject(name: 'Старое имя');

        $this->actingAs($user)
            ->putJson("/api/projects/{$project->id}", [
                'name' => 'Новое имя',
                'sitekey' => $project->sitekey,
                'host' => $project->host,
                'shared_secret' => Str::random(32),
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Новое имя');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Новое имя']);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/projects/{project}
    // -------------------------------------------------------------------------

    /**
     * Удаление проекта — 204, запись исчезает из БД.
     */
    public function test_destroy_deletes_project_and_returns_204(): void
    {
        $user = $this->makeUser('project_delete');
        $project = $this->makeProject();

        $this->actingAs($user)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    /**
     * Без пермишена project_delete — 403.
     */
    public function test_destroy_returns_403_without_permission(): void
    {
        $user = $this->makeUser('project_view');
        $project = $this->makeProject();

        $this->actingAs($user)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function makeProject(string $name = 'Project'): Project
    {
        return Project::query()->create([
            'name' => $name,
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(4).'.local',
            'shared_secret' => Str::random(32),
            'is_active' => true,
        ]);
    }
}
