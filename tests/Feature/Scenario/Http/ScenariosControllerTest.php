<?php

declare(strict_types=1);

namespace Tests\Feature\Scenario\Http;

use Spatie\Permission\PermissionRegistrar;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ScenariosControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_index_returns_active_scenarios(): void
    {
        $user = $this->makeUser('scenario_view');
        $this->makeActiveScenario('Сценарий 1');
        $this->makeActiveScenario('Сценарий 2');

        $this->actingAs($user)
            ->getJson('/api/scenarios')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_excludes_scenarios_without_active_version_by_default(): void
    {
        $user = $this->makeUser('scenario_view');
        $this->makeActiveScenario('Активный');
        Scenario::query()->create(['name' => 'Без версии', 'is_active' => true]);

        $this->actingAs($user)
            ->getJson('/api/scenarios')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_with_active_only_false_returns_all_scenarios(): void
    {
        $user = $this->makeUser('scenario_view');
        $this->makeActiveScenario('Активный');
        Scenario::query()->create(['name' => 'Без версии', 'is_active' => true]);

        $this->actingAs($user)
            ->getJson('/api/scenarios?filter[active_only]=false')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_search_query(): void
    {
        $user = $this->makeUser('scenario_view');
        Scenario::query()->create(['name' => 'unique-scenario', 'is_active' => true]);
        Scenario::query()->create(['name' => 'other-scenario', 'is_active' => true]);

        $this->actingAs($user)
            ->getJson('/api/scenarios?filter[search]=unique&filter[active_only]=false')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_is_active(): void
    {
        $user = $this->makeUser('scenario_view');
        Scenario::query()->create(['name' => 'Активный', 'is_active' => true]);
        Scenario::query()->create(['name' => 'Неактивный', 'is_active' => false]);

        $this->actingAs($user)
            ->getJson('/api/scenarios?filter[is_active]=false&filter[active_only]=false')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_status_active(): void
    {
        $user = $this->makeUser('scenario_view');
        $this->makeActiveScenario('Активный');
        Scenario::query()->create(['name' => 'Черновик', 'is_active' => true, 'status' => 'draft']);
        Scenario::query()->create(['name' => 'Архив', 'is_active' => false, 'status' => 'archived']);

        $response = $this->actingAs($user)
            ->getJson('/api/scenarios?filter[status]=active&filter[active_only]=false')
            ->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.status', 'active');
    }

    public function test_index_filters_by_status_draft(): void
    {
        $user = $this->makeUser('scenario_view');
        $this->makeActiveScenario('Активный');
        Scenario::query()->create(['name' => 'Черновик', 'is_active' => true, 'status' => 'draft']);

        $response = $this->actingAs($user)
            ->getJson('/api/scenarios?filter[status]=draft&filter[active_only]=false')
            ->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.status', 'draft');
    }

    public function test_index_filters_by_status_archived(): void
    {
        $user = $this->makeUser('scenario_view');
        $this->makeActiveScenario('Активный');
        Scenario::query()->create(['name' => 'Архив', 'is_active' => false, 'status' => 'archived']);

        $response = $this->actingAs($user)
            ->getJson('/api/scenarios?filter[status]=archived&filter[active_only]=false')
            ->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.status', 'archived');
    }

    public function test_index_ignores_unknown_status_filter(): void
    {
        $user = $this->makeUser('scenario_view');
        $this->makeActiveScenario('Активный');

        $this->actingAs($user)
            ->getJson('/api/scenarios?filter[status]=unknown&filter[active_only]=false')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_includes_status_attribute(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = $this->makeActiveScenario('Со статусом');

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.status', 'active');
    }

    public function test_new_scenario_has_draft_status(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = Scenario::query()->create(['name' => 'Черновик', 'is_active' => true]);

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.status', 'draft');
    }

    public function test_index_returns_403_without_permission(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson('/api/scenarios')
            ->assertForbidden();
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/scenarios')
            ->assertUnauthorized();
    }

    public function test_show_returns_scenario_attributes(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = Scenario::query()->create(['name' => 'Тест-шоу', 'is_active' => true]);

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Тест-шоу')
            ->assertJsonPath('data.attributes.is_active', true);
    }

    public function test_show_returns_404_for_nonexistent_scenario(): void
    {
        $user = $this->makeUser('scenario_view');

        $this->actingAs($user)
            ->getJson('/api/scenarios/'.Str::uuid())
            ->assertNotFound();
    }

    public function test_show_returns_403_without_permission(): void
    {
        $user = $this->makeUser();
        $scenario = Scenario::query()->create(['name' => 'Тест', 'is_active' => true]);

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}")
            ->assertForbidden();
    }

    public function test_store_creates_scenario_with_permission(): void
    {
        $user = $this->makeUser('scenario_create');

        $this->actingAs($user)
            ->postJson('/api/scenarios', [
                'name' => 'Новый сценарий',
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Новый сценарий');

        $this->assertDatabaseHas('scenarios', ['name' => 'Новый сценарий']);
    }

    public function test_store_persists_scenario_type(): void
    {
        $user = $this->makeUser('scenario_create');

        $this->actingAs($user)
            ->postJson('/api/scenarios', [
                'name' => 'Telegram-сценарий',
                'type' => 'telegram',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'telegram');

        $this->assertDatabaseHas('scenarios', [
            'name' => 'Telegram-сценарий',
            'type' => 'telegram',
        ]);
    }

    public function test_store_uses_colls_type_by_default(): void
    {
        $user = $this->makeUser('scenario_create');

        $this->actingAs($user)
            ->postJson('/api/scenarios', [
                'name' => 'Сценарий по умолчанию',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'colls');

        $this->assertDatabaseHas('scenarios', [
            'name' => 'Сценарий по умолчанию',
            'type' => 'colls',
        ]);
    }

    public function test_store_rejects_unknown_scenario_type(): void
    {
        $user = $this->makeUser('scenario_create');

        $this->actingAs($user)
            ->postJson('/api/scenarios', [
                'name' => 'Неизвестный тип',
                'type' => 'email',
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/type');
    }

    public function test_store_returns_403_without_permission(): void
    {
        $user = $this->makeUser('scenario_view');

        $this->actingAs($user)
            ->postJson('/api/scenarios', ['name' => 'Test', 'is_active' => true])
            ->assertForbidden();
    }

    public function test_store_returns_422_when_name_missing(): void
    {
        $user = $this->makeUser('scenario_create');

        $this->actingAs($user)
            ->postJson('/api/scenarios', ['is_active' => true])
            ->assertUnprocessable();
    }

    public function test_update_persists_new_name(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = Scenario::query()->create(['name' => 'Старое', 'is_active' => true]);

        $this->actingAs($user)
            ->putJson("/api/scenarios/{$scenario->id}", [
                'name' => 'Новое',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Новое');

        $this->assertDatabaseHas('scenarios', ['id' => $scenario->id, 'name' => 'Новое']);
    }

    public function test_update_without_type_preserves_existing_scenario_type(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = Scenario::query()->create([
            'name' => 'Telegram',
            'type' => 'telegram',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson("/api/scenarios/{$scenario->id}", [
                'name' => 'Telegram обновлён',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'telegram');

        $this->assertDatabaseHas('scenarios', [
            'id' => $scenario->id,
            'type' => 'telegram',
        ]);
    }

    public function test_update_returns_403_without_permission(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = Scenario::query()->create(['name' => 'Сценарий', 'is_active' => true]);

        $this->actingAs($user)
            ->putJson("/api/scenarios/{$scenario->id}", ['name' => 'Новое', 'is_active' => true])
            ->assertForbidden();
    }

    public function test_destroy_deletes_scenario_and_returns_204(): void
    {
        $user = $this->makeUser('scenario_delete');
        $scenario = Scenario::query()->create(['name' => 'К удалению', 'is_active' => true]);

        $this->actingAs($user)
            ->deleteJson("/api/scenarios/{$scenario->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('scenarios', ['id' => $scenario->id]);
    }

    public function test_destroy_returns_403_without_permission(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = Scenario::query()->create(['name' => 'Сценарий', 'is_active' => true]);

        $this->actingAs($user)
            ->deleteJson("/api/scenarios/{$scenario->id}")
            ->assertForbidden();
    }

    public function test_duplicate_creates_copy_with_suffix(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = Scenario::query()->create(['name' => 'Оригинал', 'is_active' => true]);

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/duplicate")
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Оригинал (копия)');

        $this->assertDatabaseHas('scenarios', ['name' => 'Оригинал (копия)']);
    }

    public function test_duplicate_preserves_scenario_type(): void
    {
        $user = $this->makeUser('scenario_create');
        $scenario = Scenario::query()->create([
            'name' => 'Бот',
            'type' => 'call_bots',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.type', 'call_bots');

        $this->assertDatabaseHas('scenarios', [
            'name' => 'Бот (копия)',
            'type' => 'call_bots',
        ]);
    }

    public function test_duplicate_returns_403_without_permission(): void
    {
        $user = $this->makeUser('scenario_view');
        $scenario = Scenario::query()->create(['name' => 'Оригинал', 'is_active' => true]);

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/duplicate")
            ->assertForbidden();
    }

    public function test_index_returns_only_scenarios_from_current_project(): void
    {
        [$user, $project] = $this->makeUserWithProject('scenario_view');
        $this->makeActiveScenario('Мой', $project->id);
        $this->makeActiveScenario('Чужой');

        $this->actingAs($user)
            ->getJson('/api/scenarios')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_persists_current_project_id(): void
    {
        [$user, $project] = $this->makeUserWithProject('scenario_create');

        $this->actingAs($user)
            ->postJson('/api/scenarios', ['name' => 'С проектом', 'is_active' => true])
            ->assertStatus(201);

        $this->assertDatabaseHas('scenarios', [
            'name' => 'С проектом',
            'project_id' => $project->id,
        ]);
    }

    public function test_show_returns_404_when_scenario_belongs_to_different_project(): void
    {
        [$user] = $this->makeUserWithProject('scenario_view');
        $otherProject = $this->makeProject();
        $scenario = Scenario::query()->create(
            ['name' => 'Чужой', 'is_active' => true, 'project_id' => $otherProject->id]
        );

        $this->actingAs($user)
            ->getJson("/api/scenarios/{$scenario->id}")
            ->assertNotFound();
    }

    public function test_update_returns_404_when_scenario_belongs_to_different_project(): void
    {
        [$user] = $this->makeUserWithProject('scenario_create');
        $otherProject = $this->makeProject();
        $scenario = Scenario::query()->create(
            ['name' => 'Чужой', 'is_active' => true, 'project_id' => $otherProject->id]
        );

        $this->actingAs($user)
            ->putJson("/api/scenarios/{$scenario->id}", ['name' => 'Взлом', 'is_active' => true])
            ->assertNotFound();
    }

    public function test_destroy_returns_404_when_scenario_belongs_to_different_project(): void
    {
        [$user] = $this->makeUserWithProject('scenario_delete');
        $otherProject = $this->makeProject();
        $scenario = Scenario::query()->create(
            ['name' => 'Чужой', 'is_active' => true, 'project_id' => $otherProject->id]
        );

        $this->actingAs($user)
            ->deleteJson("/api/scenarios/{$scenario->id}")
            ->assertNotFound();
    }

    public function test_duplicate_returns_404_when_scenario_belongs_to_different_project(): void
    {
        [$user] = $this->makeUserWithProject('scenario_create');
        $otherProject = $this->makeProject();
        $scenario = Scenario::query()->create(
            ['name' => 'Чужой', 'is_active' => true, 'project_id' => $otherProject->id]
        );

        $this->actingAs($user)
            ->postJson("/api/scenarios/{$scenario->id}/duplicate")
            ->assertNotFound();
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

    /** @return array{User, Project} */
    private function makeUserWithProject(string ...$permissions): array
    {
        $project = $this->makeProject();

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

    private function makeProject(): Project
    {
        return Project::query()->create([
            'name' => 'Project '.Str::random(4),
            'sitekey' => 'sk-'.Str::random(6),
            'host' => Str::random(4).'.local',
            'is_active' => true,
        ]);
    }

    private function makeActiveScenario(string $name, ?string $projectId = null): Scenario
    {
        $scenario = Scenario::query()->create([
            'name' => $name,
            'is_active' => true,
            'project_id' => $projectId,
        ]);

        ScenarioVersion::query()->create([
            'id' => (string)Str::uuid(),
            'scenario_id' => $scenario->id,
            'project_id' => $projectId,
            'status' => 'active',
        ]);

        return $scenario->fresh();
    }
}
