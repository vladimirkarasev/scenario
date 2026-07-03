<?php

declare(strict_types=1);

namespace Tests\Feature\Scenario\Http;

use denis660\Centrifugo\Centrifugo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;
use Module\Users\Models\User;
use Tests\TestCase;

/**
 * HTTP-тесты для SurveysController.
 * Роуты: GET /api/scenarios/surveys          (index)
 *        GET /api/scenarios/survey/{runId}   (show)
 */
final class SurveysControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance(Centrifugo::class, $this->createMock(Centrifugo::class));
    }

    // ------------------------------------------------------------------ GET /api/scenarios/surveys

    public function test_index_returns_paginated_surveys(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $this->seedRun($scenario);
        $this->seedRun($scenario);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/scenarios/surveys')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'scenario_id', 'scenario_name', 'status', 'created_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/scenarios/surveys')
            ->assertUnauthorized();
    }

    public function test_index_filters_by_status(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $active = $this->seedRun($scenario, ScenarioRunStatus::Active);
        $this->seedRun($scenario, ScenarioRunStatus::Completed);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/scenarios/surveys?filter[status]=active')
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($active->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_index_filters_by_scenario_id(): void
    {
        [$scenarioA] = $this->makeScenarioWithBlock('node_block_a');
        [$scenarioB] = $this->makeScenarioWithBlock('node_block_b');
        $runA = $this->seedRun($scenarioA);
        $this->seedRun($scenarioB);

        $response = $this->actingAs(User::factory()->create())
            ->getJson("/api/scenarios/surveys?filter[scenario_id]={$scenarioA->id}")
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($runA->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_index_respects_per_page_param(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        for ($i = 0; $i < 5; $i++) {
            $this->seedRun($scenario);
        }

        $this->actingAs(User::factory()->create())
            ->getJson('/api/scenarios/surveys?page[size]=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonCount(2, 'data');
    }

    // ------------------------------------------------------------------ GET /api/scenarios/survey/{runId}

    public function test_show_returns_full_run_payload(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $run = $this->seedRun($scenario);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/scenarios/survey/{$run->id}")
            ->assertOk()
            ->assertJsonPath('data.run.id', $run->id)
            ->assertJsonPath('data.run.status', 'active')
            ->assertJsonStructure(['data' => ['run' => ['id', 'status', 'current_node_id', 'rendered', 'steps']]]);
    }

    public function test_show_returns_404_for_unknown_run(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/scenarios/survey/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_show_requires_authentication(): void
    {
        [$scenario] = $this->makeScenarioWithBlock('node_block');
        $run = $this->seedRun($scenario);

        $this->getJson("/api/scenarios/survey/{$run->id}")
            ->assertUnauthorized();
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{Scenario, ScenarioVersion, ScenarioVersionRevision} */
    private function makeScenarioWithBlock(string $blockNodeId): array
    {
        $scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id, 'status' => 'active']);

        $revision = $this->createRevision($version, [
            'nodes_json' => [
                ['id' => 'node_start', 'type' => 'start', 'data' => []],
                ['id' => $blockNodeId, 'type' => 'block', 'data' => ['title' => 'Step', 'fields' => []]],
                ['id' => 'node_end', 'type' => 'end', 'data' => ['title' => 'Готово']],
            ],
            'edges_json' => [
                ['id' => 'e1', 'source' => 'node_start', 'target' => $blockNodeId],
                ['id' => 'e2', 'source' => $blockNodeId, 'target' => 'node_end'],
            ],
        ]);

        return [$scenario, $version, $revision];
    }

    private function seedRun(Scenario $scenario, ScenarioRunStatus $status = ScenarioRunStatus::Active): ScenarioRun
    {
        /** @var ScenarioVersion $version */
        $version = $scenario->versions()->latest()->first();
        /** @var ScenarioVersionRevision $revision */
        $revision = $version->revisions()->latest()->first();

        return ScenarioRun::query()->create([
            'scenario_id' => $scenario->id,
            'scenario_version_id' => $version->id,
            'scenario_version_revision_id' => $revision->id,
            'current_node_id' => null,
            'status' => $status,
            'context_json' => [],
        ]);
    }
}
