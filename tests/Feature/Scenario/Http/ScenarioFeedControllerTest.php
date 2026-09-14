<?php

declare(strict_types=1);

namespace Tests\Feature\Scenario\Http;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Scenario\Models\Scenario;
use Module\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ScenarioFeedControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_feed_shows_root_level_by_default(): void
    {
        $user = $this->makeUser();
        $category = Category::query()->create(['name' => 'Продажи', 'is_active' => true]);
        Scenario::query()->create([
            'name' => 'Входящий звонок',
            'type' => 'colls',
            'status' => 'active',
            'is_active' => true,
        ]);
        $nestedScenario = Scenario::query()->create([
            'name' => 'Сценарий в папке',
            'type' => 'colls',
            'status' => 'active',
            'is_active' => true,
        ]);
        $nestedScenario->categories()->attach($category->id);

        $this->actingAs($user)
            ->getJson('/api/scenarios/feed')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'folder')
            ->assertJsonPath('data.0.name', 'Продажи')
            ->assertJsonPath('data.1.type', 'scenario')
            ->assertJsonPath('data.1.name', 'Входящий звонок')
            ->assertJsonPath('data.1.scenario_type', 'colls')
            ->assertJsonPath('meta.folders_total', 1)
            ->assertJsonPath('meta.items_total', 1);
    }

    public function test_feed_applies_search_and_status_filters(): void
    {
        $user = $this->makeUser();
        Scenario::query()->create([
            'name' => 'target scenario',
            'status' => 'active',
            'is_active' => true,
        ]);
        Scenario::query()->create([
            'name' => 'target draft',
            'status' => 'draft',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/scenarios/feed?filter[search]=target&filter[status]=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'target scenario')
            ->assertJsonPath('meta.counts_by_status.active', 1)
            ->assertJsonPath('meta.counts_by_status.draft', 1);
    }

    private function makeUser(): User
    {
        $user = User::query()->create([
            'name' => 'Scenario Feed User',
            'email' => 'scenario-feed@example.test',
            'password' => 'password',
        ]);
        Permission::firstOrCreate(['name' => 'scenario_view', 'guard_name' => 'web']);
        $user->givePermissionTo('scenario_view');

        return $user;
    }
}
