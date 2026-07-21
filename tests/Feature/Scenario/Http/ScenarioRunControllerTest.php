<?php

declare(strict_types=1);

namespace Tests\Feature\Scenario\Http;

use Spatie\Permission\PermissionRegistrar;
use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Projects\Models\Project;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ScenarioRunControllerTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->instance(CentrifugoApiInterface::class, $this->createMock(CentrifugoApiInterface::class));
        $this->project = Project::query()->create([
            'name' => 'Scenario test project',
            'sitekey' => 'scenario-test',
            'host' => 'scenario.test',
            'is_active' => true,
        ]);
    }

    private function makeUser(string ...$permissions): User
    {
        $user = User::factory()->create([
            'project_id' => $this->project->id,
            'is_system' => true,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function makeScenarioWithBlock(string $blockNodeId, array $fields = []): array
    {
        $scenario = Scenario::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Test',
            'is_active' => true,
        ]);
        $version = ScenarioVersion::query()->create([
            'scenario_id' => $scenario->id,
            'project_id' => $this->project->id,
            'status' => 'active',
        ]);

        $this->createRevision($version, [
            'nodes_json' => [
                [
                    'id' => 'node_start',
                    'type' => 'start',
                    'data' => [],
                ],
                [
                    'id' => $blockNodeId,
                    'type' => 'block',
                    'data' => ['title' => 'Step', 'fields' => $fields],
                ],
                [
                    'id' => 'node_end',
                    'type' => 'end',
                    'data' => ['title' => 'Готово'],
                ],
            ],
            'edges_json' => [
                ['id' => 'e1', 'source' => 'node_start', 'target' => $blockNodeId],
                ['id' => 'e2', 'source' => $blockNodeId, 'target' => 'node_end'],
            ],
        ]);

        return [$scenario, $version];
    }

    private function createRun(Scenario $scenario): ScenarioRun
    {
        $user = $this->makeUser();
        $token = $user->createToken('scenario-test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/scenarios/runner', ['scenario_id' => $scenario->id])
            ->assertCreated();

        return ScenarioRun::query()->findOrFail($response->json('data.run.id'));
    }

    public function test_store_creates_run_and_returns_201(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $user = $this->makeUser();
        $token = $user->createToken('scenario-test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/scenarios/runner', ['scenario_id' => $scenario->id])
            ->assertCreated()
            ->assertJsonPath('data.run.status', 'active');
    }

    public function test_store_uses_service_account_bearer_to_resolve_project_and_actor(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $serviceAccount = $this->makeUser();
        $token = $serviceAccount->createToken('scenario-api')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/scenarios/runner', [
                'scenario_id' => $scenario->id,
                'user_data' => ['fio' => 'Клиент'],
            ])
            ->assertCreated();

        $runId = $response->json('data.run.id');
        $this->assertDatabaseHas('scenario_runs', [
            'id' => $runId,
            'scenario_id' => $scenario->id,
            'created_by' => $serviceAccount->id,
            'updated_by' => $serviceAccount->id,
        ]);
    }

    public function test_start_resolves_scenario_alias_inside_bearer_project(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $scenario->forceFill(['alias' => 'project-survey'])->save();
        $serviceAccount = $this->makeUser();
        $token = $serviceAccount->createToken('scenario-api')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/scenarios/runner/start', ['alias' => 'project-survey'])
            ->assertCreated();

        $this->assertDatabaseHas('scenario_runs', [
            'id' => $response->json('data.id'),
            'scenario_id' => $scenario->id,
            'created_by' => $serviceAccount->id,
        ]);
    }

    public function test_store_rejects_non_service_account(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $user = User::factory()->create(['project_id' => $this->project->id]);
        $token = $user->createToken('scenario-test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/scenarios/runner', ['scenario_id' => $scenario->id])
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'SCENARIO_SERVICE_ACCOUNT_REQUIRED');
    }

    public function test_store_rejects_service_account_without_bearer(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');

        $this->actingAs($this->makeUser())
            ->postJson('/api/scenarios/runner', ['scenario_id' => $scenario->id])
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'SCENARIO_SERVICE_ACCOUNT_REQUIRED');
    }

    public function test_store_cannot_create_run_for_scenario_from_another_project(): void
    {
        $foreignProject = Project::query()->create([
            'name' => 'Foreign',
            'sitekey' => 'foreign',
            'host' => 'foreign.test',
            'is_active' => true,
        ]);
        $foreignScenario = Scenario::query()->create([
            'project_id' => $foreignProject->id,
            'name' => 'Foreign scenario',
            'is_active' => true,
        ]);
        $this->makeScenarioWithBlock('node_block');
        $serviceAccount = $this->makeUser();
        $token = $serviceAccount->createToken('scenario-test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/scenarios/runner', ['scenario_id' => $foreignScenario->id])
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'SCENARIO_NOT_FOUND');
    }

    public function test_store_requires_existing_scenario(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('scenario-test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/scenarios/runner', ['scenario_id' => 'non-existent-uuid'])
            ->assertUnprocessable();
    }

    public function test_store_requires_authentication(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');

        $this->postJson('/api/scenarios/runner', ['scenario_id' => $scenario->id])
            ->assertUnauthorized();
    }

    public function test_show_returns_run_payload(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson("/api/scenarios/runner/{$run->id}")
            ->assertOk()
            ->assertJsonPath('data.run.id', $run->id);
    }

    public function test_show_returns_404_for_unknown_run(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson('/api/scenarios/runner/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_show_returns_404_for_run_from_another_project(): void
    {
        $foreignProject = Project::query()->create([
            'name' => 'Foreign',
            'sitekey' => 'foreign',
            'host' => 'foreign.test',
            'is_active' => true,
        ]);
        $foreignScenario = Scenario::query()->create([
            'project_id' => $foreignProject->id,
            'name' => 'Foreign scenario',
            'is_active' => true,
        ]);
        $foreignVersion = ScenarioVersion::query()->create([
            'scenario_id' => $foreignScenario->id,
            'project_id' => $foreignProject->id,
            'status' => 'active',
        ]);
        $revision = $this->createRevision($foreignVersion);
        $foreignRun = ScenarioRun::query()->create([
            'scenario_id' => $foreignScenario->id,
            'scenario_version_id' => $foreignVersion->id,
            'scenario_version_revision_id' => $revision->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->makeUser())
            ->getJson("/api/scenarios/runner/{$foreignRun->id}")
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'SCENARIO_RUN_NOT_FOUND');
    }

    public function test_continue_advances_run_with_valid_input(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'full_name', 'type' => 'input', 'required' => true],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['full_name' => 'Alice'],
            ])
            ->assertOk()
            ->assertJsonPath('data.run.status', 'completed');
    }

    public function test_continue_passes_when_nullable_field_is_absent(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'comment', 'type' => 'textarea', 'required' => false],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", ['input' => []])
            ->assertOk();
    }

    public function test_continue_passes_with_no_fields_defined(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", ['input' => []])
            ->assertOk();
    }

    public function test_continue_returns_422_when_required_field_missing(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'full_name', 'type' => 'input', 'required' => true],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", ['input' => []])
            ->assertUnprocessable()
            ->assertJsonFragment(['code' => 'VALIDATION_ERROR'])
            ->assertJsonFragment(['pointer' => '/data/attributes/full_name']);
    }

    public function test_continue_returns_422_when_required_checkbox_not_accepted(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'agree', 'type' => 'checkbox', 'required' => true],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['agree' => false],
            ])
            ->assertUnprocessable()
            ->assertJsonFragment(['pointer' => '/data/attributes/agree']);
    }

    public function test_continue_returns_422_for_invalid_email(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'email', 'type' => 'email', 'required' => true],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['email' => 'not-an-email'],
            ])
            ->assertUnprocessable()
            ->assertJsonFragment(['pointer' => '/data/attributes/email']);
    }

    public function test_continue_passes_for_valid_email(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'email', 'type' => 'email', 'required' => true],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['email' => 'user@example.com'],
            ])
            ->assertOk();
    }

    public function test_continue_returns_422_for_non_numeric_value(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'age', 'type' => 'number', 'required' => true],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['age' => 'twenty'],
            ])
            ->assertUnprocessable()
            ->assertJsonFragment(['pointer' => '/data/attributes/age']);
    }

    public function test_continue_returns_422_when_textarea_exceeds_max_length(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'bio', 'type' => 'textarea', 'required' => false, 'maxLength' => 50],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['bio' => str_repeat('x', 51)],
            ])
            ->assertUnprocessable()
            ->assertJsonFragment(['pointer' => '/data/attributes/bio']);
    }

    public function test_continue_passes_when_textarea_within_max_length(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'bio', 'type' => 'textarea', 'required' => false, 'maxLength' => 50],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['bio' => str_repeat('x', 50)],
            ])
            ->assertOk();
    }

    public function test_validation_error_messages_are_in_russian(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'name', 'type' => 'input', 'required' => true],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", ['input' => []])
            ->assertUnprocessable();

        $message = $response->json('errors.0.detail');
        $this->assertIsString($message);
        $this->assertStringContainsString('обязательно', $message);
    }

    public function test_jump_moves_run_to_specified_node(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/jump", ['node_id' => 'node_block'])
            ->assertOk()
            ->assertJsonPath('data.run.current_node_id', 'node_block');
    }

    public function test_jump_returns_422_without_node_id(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/jump", [])
            ->assertUnprocessable();
    }

    public function test_jump_returns_404_for_unknown_run(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/scenarios/runner/00000000-0000-0000-0000-000000000000/jump', [
                'node_id' => 'node_block',
            ])
            ->assertNotFound();
    }

    public function test_history_returns_transition_for_new_run(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson("/api/scenarios/runner/{$run->id}/history")
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'transition')
            ->assertJsonPath('data.0.cancelled', false);
    }

    public function test_history_contains_transition_after_continue(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'note', 'type' => 'input', 'required' => false],
        ]);
        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", ['input' => ['note' => 'hi']])
            ->assertOk();

        $this->actingAs($user)
            ->getJson("/api/scenarios/runner/{$run->id}/history")
            ->assertOk()
            ->assertJsonStructure(['data' => [['type', 'at', 'node_type', 'node_title', 'cancelled']]]);
    }

    public function test_history_includes_field_filled_event_after_continue_with_input(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
        ]);
        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['email' => 'test@example.com'],
            ])
            ->assertOk();

        $response = $this->actingAs($user)
            ->getJson("/api/scenarios/runner/{$run->id}/history")
            ->assertOk();

        $history = $response->json('data');
        $types = array_column($history, 'type');
        $this->assertContains('field_filled', $types);

        $filled = array_values(array_filter($history, fn(array $e) => $e['type'] === 'field_filled'))[0];
        $this->assertSame('test@example.com', $filled['new_value']);
    }

    public function test_history_returns_404_for_unknown_run(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson('/api/scenarios/runner/00000000-0000-0000-0000-000000000000/history')
            ->assertNotFound();
    }
}
