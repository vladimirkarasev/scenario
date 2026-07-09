<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Http;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Actions\Models\Action;
use Module\Projects\Models\Project;
use Module\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ActionCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_index_filters_categories_by_parent(): void
    {
        [$user, $project] = $this->makeUserWithProject();
        $parent = $this->makeCategory('Родитель', projectId: $project->id);
        $child = $this->makeCategory('Дочерний', parentId: $parent->id, projectId: $project->id);
        $this->makeCategory('Другой', projectId: $project->id);

        $this->actingAs($user)
            ->getJson("/api/actions/categories?filter[parent_id]={$parent->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $child->id);
    }

    public function test_store_attaches_category_to_current_project(): void
    {
        [$user, $project] = $this->makeUserWithProject('category_create');

        $response = $this->actingAs($user)
            ->postJson('/api/actions/categories', [
                'name' => 'Интеграции',
                'is_active' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('model_has_categories', [
            'category_id' => $response->json('data.id'),
            'model_type' => Action::class,
            'project_id' => $project->id,
        ]);
    }

    public function test_destroy_rejects_system_category(): void
    {
        [$user, $project] = $this->makeUserWithProject('category_create');
        $category = $this->makeCategory('Системный', projectId: $project->id, isSystem: true);

        $this->actingAs($user)
            ->deleteJson("/api/actions/categories/{$category->id}")
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'ACTION_SYSTEM_CATEGORY_DELETE_FORBIDDEN')
            ->assertJsonPath('errors.0.title', 'Удаление запрещено')
            ->assertJsonStructure([
                'errors' => [['status', 'code', 'title', 'detail']],
                'meta' => ['timestamp', 'requestId'],
            ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_show_hides_category_from_another_project(): void
    {
        [$user] = $this->makeUserWithProject();
        $category = $this->makeCategory('Чужой раздел', projectId: (string) Str::uuid());

        $this->actingAs($user)
            ->getJson("/api/actions/categories/{$category->id}")
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'ACTION_CATEGORY_NOT_FOUND');
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

    private function makeCategory(
        string $name,
        ?string $parentId = null,
        ?string $projectId = null,
        bool $isSystem = false,
    ): Category {
        $category = Category::query()->create([
            'name' => $name,
            'parent_id' => $parentId,
            'is_active' => true,
            'is_system' => $isSystem,
        ]);

        DB::table('model_has_categories')->insertOrIgnore([
            'category_id' => $category->id,
            'model_id' => $category->id,
            'model_type' => Action::class,
            'project_id' => $projectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $category;
    }
}
