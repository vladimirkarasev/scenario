<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunStep;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\ScenarioRunStepManager;
use Tests\TestCase;

final class ScenarioRunStepManagerTest extends TestCase
{
    use RefreshDatabase;

    private ScenarioRunStepManager $manager;

    private ScenarioRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(ScenarioRunStepManager::class);

        $scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id]);
        $revision = $this->createRevision($version, [
            'nodes_json' => [
                ['id' => 'node_start', 'type' => 'start', 'data' => []],
                ['id' => 'node_block', 'type' => 'block', 'data' => ['title' => 'Block']],
                ['id' => 'node_end', 'type' => 'end', 'data' => []],
            ],
            'edges_json' => [
                ['id' => 'e1', 'source' => 'node_start', 'target' => 'node_block'],
                ['id' => 'e2', 'source' => 'node_block', 'target' => 'node_end'],
            ],
        ]);

        $this->run = ScenarioRun::query()->create([
            'scenario_id' => $scenario->id,
            'scenario_version_id' => $version->id,
            'scenario_version_revision_id' => $revision->id,
            'current_node_id' => 'node_block',
            'status' => 'active',
            'context' => [],
        ]);
    }

    public function test_ensure_open_creates_step_for_node(): void
    {
        $this->manager->ensureOpen($this->run, ['id' => 'node_block', 'type' => 'block', 'data' => []]);

        $step = ScenarioRunStep::query()
            ->where('run_id', $this->run->id)
            ->where('node_id', 'node_block')
            ->first();

        $this->assertNotNull($step);
        $this->assertNotNull($step->entered_at);
        $this->assertNull($step->exited_at);
    }

    public function test_ensure_open_does_not_create_duplicate_step(): void
    {
        $node = ['id' => 'node_block', 'type' => 'block', 'data' => []];

        $this->manager->ensureOpen($this->run, $node);
        $this->manager->ensureOpen($this->run, $node);

        $count = ScenarioRunStep::query()
            ->where('run_id', $this->run->id)
            ->where('node_id', 'node_block')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_create_auto_creates_completed_step(): void
    {
        $this->manager->createAuto(
            $this->run,
            ['id' => 'node_block', 'type' => 'block', 'data' => []],
            ['result' => 'ok'],
        );

        $step = ScenarioRunStep::query()
            ->where('run_id', $this->run->id)
            ->where('node_id', 'node_block')
            ->first();

        $this->assertNotNull($step);
        $this->assertNotNull($step->entered_at);
        $this->assertNotNull($step->exited_at);
        $this->assertSame(['result' => 'ok'], $step->output);
    }

    public function test_close_open_fills_input_output_and_exits_existing_step(): void
    {
        $this->manager->ensureOpen($this->run, ['id' => 'node_block', 'type' => 'block', 'data' => []]);

        $this->manager->closeOpen(
            $this->run,
            ['field' => 'value'],
            ['next_node_id' => 'node_end'],
        );

        $step = ScenarioRunStep::query()
            ->where('run_id', $this->run->id)
            ->where('node_id', 'node_block')
            ->first();

        $this->assertNotNull($step);
        $this->assertSame(['field' => 'value'], $step->input);
        $this->assertSame(['next_node_id' => 'node_end'], $step->output);
        $this->assertNotNull($step->exited_at);
    }

    public function test_close_open_creates_step_if_none_exists(): void
    {
        $this->manager->closeOpen($this->run, [], ['completed' => true]);

        $step = ScenarioRunStep::query()
            ->where('run_id', $this->run->id)
            ->where('node_id', 'node_block')
            ->first();

        $this->assertNotNull($step);
        $this->assertNotNull($step->exited_at);
    }
}
