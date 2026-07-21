<?php

declare(strict_types=1);

namespace Tests\Feature\Directories\Http;

use Spatie\Permission\PermissionRegistrar;
use App\Models\Category;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Directories\Models\Directory;
use Module\Projects\Models\Project;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class DirectoryCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_index_returns_categories(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $this->makeCategory('Авто', projectId: $project->id);
        $this->makeCategory('Мото', projectId: $project->id);

        $this->actingAs($user)
            ->getJson('/api/directories/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_excludes_categories_from_other_project(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $this->makeCategory('Мой', projectId: $project->id);
        $this->makeCategory('Чужой');

        $this->actingAs($user)
            ->getJson('/api/directories/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_returns_403_without_permission(): void
    {
        [$user] = $this->makeUserWithProject();

        $this->actingAs($user)
            ->getJson('/api/directories/categories')
            ->assertForbidden();
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/directories/categories')
            ->assertUnauthorized();
    }

    public function test_show_returns_category_attributes(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $category = $this->makeCategory('Авто', projectId: $project->id);

        $this->actingAs($user)
            ->getJson("/api/directories/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Авто');
    }

    public function test_show_returns_404_for_nonexistent_category(): void
    {
        [$user] = $this->makeUserWithProject('directory_view');

        $this->actingAs($user)
            ->getJson('/api/directories/categories/'.Str::uuid())
            ->assertNotFound();
    }

    public function test_store_creates_category(): void
    {
        [$user] = $this->makeUserWithProject('directory_create');

        $this->actingAs($user)
            ->postJson('/api/directories/categories', [
                'name' => 'Новая категория',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.name', 'Новая категория');

        $this->assertDatabaseHas('categories', ['name' => 'Новая категория']);
    }

    public function test_store_creates_nested_category(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $parent = $this->makeCategory('Родитель', projectId: $project->id);

        $this->actingAs($user)
            ->postJson('/api/directories/categories', [
                'name' => 'Дочерняя',
                'parent_id' => $parent->id,
                'is_active' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('categories', [
            'name' => 'Дочерняя',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_store_persists_project_id_in_model_has_categories(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');

        $response = $this->actingAs($user)
            ->postJson('/api/directories/categories', [
                'name' => 'Новая категория',
                'is_active' => true,
            ])
            ->assertCreated();

        $categoryId = $response->json('data.id');

        $this->assertDatabaseHas('model_has_categories', [
            'category_id' => $categoryId,
            'model_type' => Directory::class,
            'project_id' => $project->id,
        ]);
    }

    public function test_store_returns_422_when_name_missing(): void
    {
        [$user] = $this->makeUserWithProject('directory_create');

        $this->actingAs($user)
            ->postJson('/api/directories/categories', [])
            ->assertUnprocessable();
    }

    public function test_store_returns_403_without_permission(): void
    {
        [$user] = $this->makeUserWithProject('directory_view');

        $this->actingAs($user)
            ->postJson('/api/directories/categories', ['name' => 'Test', 'is_active' => true])
            ->assertForbidden();
    }

    public function test_update_persists_new_name(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_create');
        $category = $this->makeCategory('Старое имя', projectId: $project->id);

        $this->actingAs($user)
            ->putJson("/api/directories/categories/{$category->id}", [
                'name' => 'Новое имя',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Новое имя');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Новое имя']);
    }

    public function test_destroy_deletes_category_and_returns_204(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_delete');
        $category = $this->makeCategory('Удаляемая', projectId: $project->id);

        $this->actingAs($user)
            ->deleteJson("/api/directories/categories/{$category->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_destroy_returns_403_without_permission(): void
    {
        [$user, $project] = $this->makeUserWithProject('directory_view');
        $category = $this->makeCategory('Защищённая', projectId: $project->id);

        $this->actingAs($user)
            ->deleteJson("/api/directories/categories/{$category->id}")
            ->assertForbidden();
    }

    /** @return array{User, Project} */
    private function makeUserWithProject(string ...$permissions): array
    {
        $project = Project::query()->create([
            'name' => 'Project '.Str::random(4),
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(4).'.local',
            'is_active' => true,
        ]);

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

    private function makeCategory(string $name, ?string $parentId = null, ?string $projectId = null): Category
    {
        $category = Category::query()->create([
            'id' => (string)Str::uuid(),
            'name' => $name,
            'parent_id' => $parentId,
            'is_active' => true,
        ]);

        DB::table('model_has_categories')->insertOrIgnore([
            'category_id' => $category->id,
            'model_id' => $category->id,
            'model_type' => Directory::class,
            'project_id' => $projectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $category;
    }
}
