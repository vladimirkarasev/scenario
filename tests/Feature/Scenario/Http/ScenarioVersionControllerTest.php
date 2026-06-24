<?php

declare(strict_types=1);

namespace Tests\Feature\Scenario\Http;

use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HTTP-тесты для ScenariosVersionController — список версий.
 * Роут: GET /api/scenarios/{scenario}/versions
 */
final class ScenarioVersionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------
    // GET /api/scenarios/{scenario}/versions
    // -------------------------------------------------------------------------

    /**
     * Список версий сценария возвращается в data.
     */
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

    /**
     * Версии другого сценария не попадают в ответ.
     */
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

    /**
     * Ответ содержит name и status в attributes.
     */
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

    /**
     * Несуществующий сценарий — 404.
     */
    public function test_index_returns_404_for_nonexistent_scenario(): void
    {
        $user = $this->makeUser('scenario_view');

        $this->actingAs($user)
            ->getJson('/api/scenarios/'.Str::uuid().'/versions')
            ->assertNotFound();
    }

    /**
     * Без пермишена scenario_view — 403.
     */
    public function test_index_returns_403_without_permission(): void
    {
        $user = $this->makeUser();
        $scenario = $this->makeScenario();

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}/versions")
            ->assertForbidden();
    }

    /**
     * Без авторизации — 401.
     */
    public function test_index_requires_authentication(): void
    {
        $scenario = $this->makeScenario();

        $this->getJson("/api/scenarios/{$scenario->id}/versions")
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // GET /api/scenarios/{scenario}/versions/{version}
    // -------------------------------------------------------------------------

    /**
     * show возвращает одну версию с её атрибутами.
     */
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

    /**
     * show возвращает 404 для несуществующей версии.
     */
    public function test_show_returns_404_for_missing_version(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}/versions/".Str::uuid())
            ->assertNotFound();
    }

    /**
     * show без пермишена — 403.
     */
    public function test_show_returns_403_without_permission(): void
    {
        $user = $this->makeUser();
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1');

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}/versions/{$version->id}")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // POST /api/scenarios/{scenario}/versions
    // -------------------------------------------------------------------------

    /**
     * store создаёт версию и возвращает 201 с item.
     */
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
            ->assertJsonPath('item.name', 'Beta')
            ->assertJsonPath('item.status', 'draft');

        $this->assertDatabaseHas('scenario_versions', [
            'scenario_id' => $scenario->id,
            'name' => 'Beta',
        ]);
    }

    /**
     * store без name автоматически генерирует имя vN.
     */
    public function test_store_generates_name_when_omitted(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = $this->makeScenario();

        $response = $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/versions", [
                'schema_json' => ['blocks' => [], 'connections' => [], 'version' => 1],
            ])
            ->assertStatus(201);

        $this->assertNotNull($response->json('item.name'));
    }

    /**
     * store со status=active синхронизирует статус сценария через Observer.
     */
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

    /**
     * store без schema_json — 422.
     */
    public function test_store_returns_422_when_schema_json_missing(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = $this->makeScenario();

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/versions", ['name' => 'X'])
            ->assertUnprocessable();
    }

    /**
     * store без пермишена — 403.
     */
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

    // -------------------------------------------------------------------------
    // PUT /api/scenarios/{scenario}/versions/{version}
    // -------------------------------------------------------------------------

    /**
     * update изменяет имя и статус версии.
     */
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
            ->assertJsonPath('item.name', 'v1 — исправлена')
            ->assertJsonPath('item.status', 'active');

        $this->assertDatabaseHas('scenario_versions', [
            'id' => $version->id,
            'name' => 'v1 — исправлена',
            'status' => 'active',
        ]);
    }

    /**
     * update без schema_json — 422.
     */
    public function test_update_returns_422_when_schema_json_missing(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1');

        $this->actingAs($user)
            ->putJson("/api/scenarios/{$scenario->id}/versions/{$version->id}", ['name' => 'X'])
            ->assertUnprocessable();
    }

    /**
     * update без пермишена — 403.
     */
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

    // -------------------------------------------------------------------------
    // POST /api/scenarios/{scenario}/versions/{version}/duplicate
    // -------------------------------------------------------------------------

    /**
     * duplicate создаёт копию версии со статусом draft и суффиксом «(копия)».
     */
    public function test_duplicate_creates_copy_with_draft_status(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1', 'active');

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/versions/{$version->id}/duplicate")
            ->assertStatus(201)
            ->assertJsonPath('item.name', 'v1 (копия)')
            ->assertJsonPath('item.status', 'draft');

        $this->assertDatabaseHas('scenario_versions', [
            'scenario_id' => $scenario->id,
            'name' => 'v1 (копия)',
            'status' => 'draft',
        ]);
    }

    /**
     * duplicate без пермишена — 403.
     */
    public function test_duplicate_returns_403_without_permission(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1');

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/versions/{$version->id}/duplicate")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // DELETE /api/scenarios/{scenario}/versions/{version}
    // -------------------------------------------------------------------------

    /**
     * destroy удаляет версию и возвращает scenario_id.
     */
    public function test_destroy_deletes_version(): void
    {
        $user = $this->makeUser('scenario_delete');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1');

        $this->actingAs($user)
            ->deleteJson("/api/scenarios/{$scenario->id}/versions/{$version->id}")
            ->assertOk()
            ->assertJsonPath('scenario_id', $scenario->id);

        $this->assertDatabaseMissing('scenario_versions', ['id' => $version->id]);
    }

    /**
     * destroy без пермишена — 403.
     */
    public function test_destroy_returns_403_without_permission(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeScenario();
        $version = $this->makeVersion($scenario, 'v1');

        $this->actingAs($user)
            ->deleteJson("/api/scenarios/{$scenario->id}/versions/{$version->id}")
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
