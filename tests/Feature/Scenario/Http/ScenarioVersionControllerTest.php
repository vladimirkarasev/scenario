<?php

declare(strict_types=1);

namespace Tests\Feature\Scenario\Http;

use Spatie\Permission\PermissionRegistrar;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ScenarioVersionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_index_returns_versions_for_scenario(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();
        $this->makeVersion($scenario, 'v1');
        $this->makeVersion($scenario, 'v2');

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}/versions")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_excludes_versions_from_other_scenarios(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();
        $other = $this->makeScenario();
        $this->makeVersion($scenario, 'v1');
        $this->makeVersion($other, 'v1');

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}/versions")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_returns_version_attributes(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();
        $this->makeVersion($scenario, 'v1', status: 'active');

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}/versions")
            ->assertOk()
            ->assertJsonPath('data.0.attributes.name', 'v1')
            ->assertJsonPath('data.0.attributes.status', 'active');
    }

    public function test_index_returns_404_for_nonexistent_scenario(): void
    {
        $user = $this->makeUser('scenario_view');

        $this->actingAs($user)
            ->getJson('/api/scenarios/'.Str::uuid().'/versions')
            ->assertNotFound();
    }

    public function test_index_returns_403_without_permission(): void
    {
        $user = $this->makeUser();
        $scenario = $this->makeScenario();

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}/versions")
            ->assertForbidden();
    }

    public function test_index_requires_authentication(): void
    {
        $scenario = $this->makeScenario();

        $this->getJson("/api/scenarios/{$scenario->id}/versions")
            ->assertUnauthorized();
    }

    public function test_show_returns_version(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1', 'active');

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}/versions/{$version->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $version->id)
            ->assertJsonPath('data.attributes.name', 'v1')
            ->assertJsonPath('data.attributes.status', 'active')
            ->assertJsonPath('data.attributes.scenario_id', $scenario->id);
    }

    public function test_show_returns_404_for_missing_version(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}/versions/".Str::uuid())
            ->assertNotFound();
    }

    public function test_show_returns_403_without_permission(): void
    {
        $user = $this->makeUser();
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1');

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}/versions/{$version->id}")
            ->assertForbidden();
    }

    public function test_store_creates_version_and_returns_201(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = $this->makeScenario();

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/versions", [
                'name' => 'Beta',
                'status' => 'draft',
                'schema_json' => ['blocks' => [], 'connections' => [], 'version' => 1],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Beta')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('scenario_versions', [
            'scenario_id' => $scenario->id,
            'name' => 'Beta',
        ]);
    }

    public function test_store_generates_name_when_omitted(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = $this->makeScenario();

        $response = $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/versions", [
                'schema_json' => ['blocks' => [], 'connections' => [], 'version' => 1],
            ])
            ->assertStatus(201);

        $this->assertNotNull($response->json('data.name'));
    }

    public function test_store_with_active_status_sets_scenario_active(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = $this->makeScenario();

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/versions", [
                'status' => 'active',
                'schema_json' => ['blocks' => [], 'connections' => [], 'version' => 1],
            ])
            ->assertStatus(201);

        $this->assertEquals('active', $scenario->fresh()?->status->value);
    }

    public function test_store_returns_422_when_schema_json_missing(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = $this->makeScenario();

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/versions", ['name' => 'X'])
            ->assertUnprocessable();
    }

    public function test_store_returns_403_without_permission(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/versions", [
                'schema_json' => ['blocks' => [], 'connections' => [], 'version' => 1],
            ])
            ->assertForbidden();
    }

    public function test_update_changes_name_and_status(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1', 'draft');

        $this->actingAs($user)
            ->putJson("/api/scenarios/{$scenario->id}/versions/{$version->id}", [
                'name' => 'v1 — исправлена',
                'status' => 'active',
                'schema_json' => ['blocks' => [], 'connections' => [], 'version' => 1],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'v1 — исправлена')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('scenario_versions', [
            'id' => $version->id,
            'name' => 'v1 — исправлена',
            'status' => 'active',
        ]);
    }

    public function test_update_returns_422_when_schema_json_missing(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1');

        $this->actingAs($user)
            ->putJson("/api/scenarios/{$scenario->id}/versions/{$version->id}", ['name' => 'X'])
            ->assertUnprocessable();
    }

    public function test_update_returns_403_without_permission(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1');

        $this->actingAs($user)
            ->putJson("/api/scenarios/{$scenario->id}/versions/{$version->id}", [
                'schema_json' => ['blocks' => [], 'connections' => [], 'version' => 1],
            ])
            ->assertForbidden();
    }

    public function test_duplicate_creates_copy_with_draft_status(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1', 'active');

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/versions/{$version->id}/duplicate")
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'v1 (копия)')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('scenario_versions', [
            'scenario_id' => $scenario->id,
            'name' => 'v1 (копия)',
            'status' => 'draft',
        ]);
    }

    public function test_duplicate_returns_403_without_permission(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1');

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/versions/{$version->id}/duplicate")
            ->assertForbidden();
    }

    public function test_destroy_deletes_version(): void
    {
        $user = $this->makeUser('scenario_delete');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1');

        $this->actingAs($user)
            ->deleteJson("/api/scenarios/{$scenario->id}/versions/{$version->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('scenario_versions', ['id' => $version->id]);
    }

    public function test_destroy_returns_403_without_permission(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1');

        $this->actingAs($user)
            ->deleteJson("/api/scenarios/{$scenario->id}/versions/{$version->id}")
            ->assertForbidden();
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

    private function makeScenario(): Scenario
    {
        return Scenario::query()->create(['name' => 'Scenario '.Str::random(4), 'is_active' => true]);
    }

    private function makeVersion(Scenario $scenario, string $name, string $status = 'draft'): ScenarioVersion
    {
        return ScenarioVersion::query()->create([
            'id' => (string)Str::uuid(),
            'scenario_id' => $scenario->id,
            'name' => $name,
            'status' => $status,
        ]);
    }
}
