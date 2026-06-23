<?php

declare(strict_types=1);

namespace Tests\Feature\Scenario\Http;

use App\Models\User;
use denis660\Centrifugo\Centrifugo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * HTTP-тесты для ScenarioRunController.
 * Роуты: POST /api/scenarios/runner         (store)
 *        GET  /api/scenarios/runner/{id}    (show)
 *        POST /api/scenarios/runner/{id}/continue (continue)
 */
final class ScenarioRunControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->instance(Centrifugo::class, $this->createMock(Centrifugo::class));
    }

    // ------------------------------------------------------------------ helpers

    private function makeUser(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function makeScenarioWithBlock(string $blockNodeId, array $fields = []): array
    {
        $scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id, 'status' => 'active']);

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

        $response = $this->actingAs($user)
            ->postJson('/api/scenarios/runner', ['scenario_id' => $scenario->id])
            ->assertCreated();

        return ScenarioRun::query()->findOrFail($response->json('run.id'));
    }

    // ------------------------------------------------------------------ POST /api/scenarios/runner

    public function test_store_creates_run_and_returns_201(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/scenarios/runner', ['scenario_id' => $scenario->id])
            ->assertCreated()
            ->assertJsonPath('run.status', 'active');
    }

    public function test_store_requires_existing_scenario(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/scenarios/runner', ['scenario_id' => 'non-existent-uuid'])
            ->assertUnprocessable();
    }

    public function test_store_requires_authentication(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');

        $this->postJson('/api/scenarios/runner', ['scenario_id' => $scenario->id])
            ->assertUnauthorized();
    }

    // ------------------------------------------------------------------ GET /api/scenarios/runner/{id}

    public function test_show_returns_run_payload(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson("/api/scenarios/runner/{$run->id}")
            ->assertOk()
            ->assertJsonPath('run.id', $run->id);
    }

    public function test_show_returns_404_for_unknown_run(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson('/api/scenarios/runner/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    // ------------------------------------------------------------------ POST /api/scenarios/runner/{id}/continue
    // — happy paths

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
            ->assertJsonPath('run.status', 'completed');
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

    // ------------------------------------------------------------------ required

    public function test_continue_returns_422_when_required_field_missing(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'full_name', 'type' => 'input', 'required' => true],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", ['input' => []])
            ->assertUnprocessable();

        $this->assertArrayHasKey('full_name', $response->json('errors'));
    }

    public function test_continue_returns_422_when_required_checkbox_not_accepted(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'agree', 'type' => 'checkbox', 'required' => true],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['agree' => false],
            ])
            ->assertUnprocessable();

        $this->assertArrayHasKey('agree', $response->json('errors'));
    }

    // ------------------------------------------------------------------ email

    public function test_continue_returns_422_for_invalid_email(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'email', 'type' => 'email', 'required' => true],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['email' => 'not-an-email'],
            ])
            ->assertUnprocessable();

        $this->assertArrayHasKey('email', $response->json('errors'));
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

    // ------------------------------------------------------------------ number

    public function test_continue_returns_422_for_non_numeric_value(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'age', 'type' => 'number', 'required' => true],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['age' => 'twenty'],
            ])
            ->assertUnprocessable();

        $this->assertArrayHasKey('age', $response->json('errors'));
    }

    // ------------------------------------------------------------------ textarea maxLength

    public function test_continue_returns_422_when_textarea_exceeds_max_length(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block', [
            ['name' => 'bio', 'type' => 'textarea', 'required' => false, 'maxLength' => 50],
        ]);

        $run = $this->createRun($scenario);
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->postJson("/api/scenarios/runner/{$run->id}/continue", [
                'input' => ['bio' => str_repeat('x', 51)],
            ])
            ->assertUnprocessable();

        $this->assertArrayHasKey('bio', $response->json('errors'));
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

    // ------------------------------------------------------------------ error message locale

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

        $message = $response->json('errors.name.0');
        $this->assertNotNull($message);
        $this->assertStringContainsString('обязательно', $message);
    }
}
