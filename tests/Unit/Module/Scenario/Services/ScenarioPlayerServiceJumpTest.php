<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\DTO\ScenarioRunJumpData;
use Module\Scenario\Enums\ScenarioContextKey;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\ScenarioPlayerService;
use Tests\TestCase;

final class ScenarioPlayerServiceJumpTest extends TestCase
{
    use RefreshDatabase;

    private ScenarioPlayerService $player;

    private Scenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = app(ScenarioPlayerService::class);

        $this->scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $version = ScenarioVersion::query()->create(['scenario_id' => $this->scenario->id, 'status' => 'active']);

        $this->createRevision($version, [
            'nodes_json' => [
                ['id' => 'node_start', 'type' => 'start', 'data' => []],
                ['id' => 'node_block', 'type' => 'block', 'data' => ['title' => 'Block']],
                ['id' => 'node_action', 'type' => 'action', 'data' => ['wait_for_result' => true]],
                ['id' => 'node_end', 'type' => 'end', 'data' => ['title' => 'Готово']],
            ],
            'edges_json' => [
                ['id' => 'e1', 'source' => 'node_start', 'target' => 'node_block'],
                ['id' => 'e2', 'source' => 'node_block', 'target' => 'node_action'],
                ['id' => 'e3', 'source' => 'node_action', 'target' => 'node_end'],
            ],
        ]);
    }

    public function test_jump_clears_action_node_pipeline_state(): void
    {
        $run = $this->createRun();
        $this->assertSame('node_block', $run->current_node_id);

        $context = $run->context ?? [];
        $context[ScenarioContextKey::ActionRuns->value] = ['node_action' => 'done'];
        $context[ScenarioContextKey::ActionStages->value] = ['node_action' => ['send' => 'success']];
        $run->forceFill(['context' => $context])->save();

        $run = $this->player->jumpRun($run, new ScenarioRunJumpData('node_block'));

        $this->assertSame('node_block', $run->current_node_id);
        $this->assertSame('active', $run->status->value);

        $freshContext = $this->context($run);
        $this->assertArrayNotHasKey(ScenarioContextKey::ActionRuns->value, $freshContext);
        $this->assertArrayNotHasKey(ScenarioContextKey::ActionStages->value, $freshContext);
    }

    public function test_action_node_reruns_after_jump_instead_of_being_skipped(): void
    {
        $run = $this->createRun();

        $run = $this->player->continueRun($run, new ScenarioRunContinueData([], null));
        $actionRuns = $this->context($run)[ScenarioContextKey::ActionRuns->value] ?? [];
        $this->assertIsArray($actionRuns);
        $this->assertSame('done', $actionRuns['node_action'] ?? null);

        $run = $this->player->jumpRun($run, new ScenarioRunJumpData('node_block'));

        $this->assertSame('node_block', $run->current_node_id);
        $afterJump = $this->context($run)[ScenarioContextKey::ActionRuns->value] ?? [];
        $this->assertIsArray($afterJump);
        $this->assertArrayNotHasKey('node_action', $afterJump);
    }

    /** @return array<string, mixed> */
    private function context(ScenarioRun $run): array
    {
        return is_array($run->context) ? $run->context : [];
    }

    private function createRun(): ScenarioRun
    {
        return $this->player->createRun(new ScenarioRunData(
            scenarioId: $this->scenario->id,
            scenarioVersionId: null,
            context: [],
            userData: [],
        ));
    }
}
