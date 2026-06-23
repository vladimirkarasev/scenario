<?php

declare(strict_types=1);

namespace Tests\Feature\Directories\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HTTP-тесты CRUD справочников.
 * Роуты: GET|POST /api/directories, GET|PUT|DELETE /api/directories/{directory}
 */
final class DirectoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------
    // GET /api/directories
    // -------------------------------------------------------------------------

    /**
     * Список возвращает справочники текущего проекта пользователя.
     */
    public function test_index_returns_directories_for_user_project(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $this->makeDirectory($project);
        $this->makeDirectory($project);

        $this->actingAs($user)
            ->getJson('/api/directories')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Справочники другого проекта не попадают в ответ.
     */
    public function test_index_excludes_directories_from_other_projects(): void
    {
        [$user] = $this->makeUserWithProject('directory_view');
        $otherProject = $this->makeProject();
        $this->makeDirectory($otherProject);

        $this->actingAs($user)
            ->getJson('/api/directories')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Без пермишена directory_view — 403.
     */
    public function test_index_returns_403_without_permission(): void
    {
        [$user] = $this->makeUserWithProject();

        $this->actingAs($user)
            ->getJson('/api/directories')
            ->assertForbidden();
    }

    /**
     * Без авторизации — 401.
     */
    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/directories')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // GET /api/directories/{directory}
    // -------------------------------------------------------------------------

    /**
     * Существующий справочник возвращает name и slug в attributes.
     */
    public function test_show_returns_directory_attributes(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project, name: 'Тест', slug: 'test-dir');

        $this->actingAs($user)
            ->getJson("/api/directories/{$directory->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Тест')
            ->assertJsonPath('data.attributes.slug', 'test-dir');
    }

    /**
     * Справочник из чужого проекта — DirectoryException::notInProject() → 404.
     */
    public function test_show_denies_access_to_directory_from_other_project(): void
    {
        [$user] = $this->makeUserWithProject('directory_view');
        $other = $this->makeDirectory($this->makeProject());

        $this->actingAs($user)
            ->getJson("/api/directories/{$other->id}")
            ->assertNotFound();
    }

    /**
     * Несуществующий ID — 404.
     */
    public function test_show_returns_404_for_nonexistent_directory(): void
    {
        [$user] = $this->makeUserWithProject('directory_view');

        $this->actingAs($user)
            ->getJson('/api/directories/' . Str::uuid())
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // POST /api/directories
    // -------------------------------------------------------------------------

    /**
     * Валидные данные — 201, запись появляется в БД.
     */
    public function test_store_creates_directory_and_returns_201(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');

        $response = $this->actingAs($user)
            ->postJson('/api/directories', [
                'name'        => 'Новый справочник',
                'slug'        => 'new-directory',
                'source_type' => 'manual',
                'fields'      => [],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.attributes.name', 'Новый справочник');

        $this->assertDatabaseHas('directories', [
            'project_id' => $project->id,
            'slug'       => 'new-directory',
        ]);
    }

    /**
     * Пустое тело — 422 из-за отсутствия name и slug.
     */
    public function test_store_returns_422_when_required_fields_missing(): void
    {
        [$user] = $this->makeUserWithProject('directory_create');

        $this->actingAs($user)
            ->postJson('/api/directories', [])
            ->assertUnprocessable();
    }

    /**
     * Без пермишена directory_create — 403.
     */
    public function test_store_returns_403_without_permission(): void
    {
        [$user] = $this->makeUserWithProject('directory_view');

        $this->actingAs($user)
            ->postJson('/api/directories', [
                'name' => 'Test', 'slug' => 'test', 'source_type' => 'manual', 'fields' => [],
            ])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // PUT /api/directories/{directory}
    // -------------------------------------------------------------------------

    /**
     * Обновление name и description сохраняется в БД.
     */
    public function test_update_persists_new_values(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $directory = $this->makeDirectory($project, name: 'Старое');

        $this->actingAs($user)
            ->putJson("/api/directories/{$directory->id}", [
                'name'        => 'Новое',
                'slug'        => 'new-slug',
                'source_type' => 'manual',
                'fields'      => [['key' => 'name', 'name' => 'Name', 'type' => 'string']],
            ])
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Новое');

        $this->assertDatabaseHas('directories', ['id' => $directory->id, 'name' => 'Новое']);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/directories/{directory}
    // -------------------------------------------------------------------------

    /**
     * Удаление — 204, запись исчезает из БД.
     */
    public function test_destroy_deletes_directory_and_returns_204(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_delete');
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->deleteJson("/api/directories/{$directory->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('directories', ['id' => $directory->id]);
    }

    /**
     * Без пермишена directory_delete — 403.
     */
    public function test_destroy_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $directory = $this->makeDirectory($project);

        $this->actingAs($user)
            ->deleteJson("/api/directories/{$directory->id}")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array{User, Project} */
    private function makeUserWithProject(string ...$permissions): array
    {
        $project = $this->makeProject();
        $user    = User::factory()->create([
            'sitekey' => $project->sitekey,
            'host'    => $project->host,
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
            'name'      => 'Project ' . Str::random(4),
            'sitekey'   => 'sk-' . Str::random(6),
            'host'      => Str::random(4) . '.local',
            'is_active' => true,
        ]);
    }

    private function makeDirectory(Project $project, string $name = 'Directory', string $slug = ''): Directory
    {
        return Directory::query()->create([
            'project_id'  => $project->id,
            'name'        => $name,
            'slug'        => $slug ?: 'dir-' . Str::random(6),
            'source_type' => 'manual',
        ]);
    }
}
