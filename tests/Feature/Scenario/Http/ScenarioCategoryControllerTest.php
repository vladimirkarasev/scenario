<?php

declare(strict_types=1);

namespace Tests\Feature\Scenario\Http;

use App\Models\Category;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ScenarioCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_filter_returns_empty_when_no_workspace_section(): void
    {
        $user = $this->makeUser('scenario_view');

        $this->actingAs($user)
            ->getJson('/api/scenarios/categories?filter[is_workspace]=true')
            ->assertSuccessful()
            ->assertJsonCount(0, 'data');
    }

    public function test_workspace_filter_returns_the_flagged_section(): void
    {
        $user = $this->makeUser('scenario_view', 'scenario_create', 'category_create');

        $id = $this->actingAs($user)
            ->postJson('/api/scenarios/categories', [
                'name' => 'Рабочая',
                'is_active' => true,
                'is_workspace' => true,
            ])
            ->json('data.id');

        $this->actingAs($user)
            ->getJson('/api/scenarios/categories?filter[is_workspace]=true')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.attributes.is_workspace', true);
    }

    public function test_only_one_workspace_section_per_project(): void
    {
        $user = $this->makeUser('scenario_create', 'category_create');

        $first = $this->actingAs($user)
            ->postJson('/api/scenarios/categories', [
                'name' => 'Первая',
                'is_active' => true,
                'is_workspace' => true,
            ])
            ->json('data.id');

        $second = $this->actingAs($user)
            ->postJson('/api/scenarios/categories', [
                'name' => 'Вторая',
                'is_active' => true,
                'is_workspace' => true,
            ])
            ->json('data.id');

        $this->assertFalse(Category::query()->findOrFail($first)->is_workspace);
        $this->assertTrue(Category::query()->findOrFail($second)->is_workspace);
        $this->assertSame(1, Category::query()->where('is_workspace', true)->count());
    }

    private function makeUser(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}
