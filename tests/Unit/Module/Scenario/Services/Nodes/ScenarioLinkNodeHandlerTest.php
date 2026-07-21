<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\ScenarioLink\ScenarioLinkNodeHandler;
use RuntimeException;
use Tests\TestCase;

final class ScenarioLinkNodeHandlerTest extends TestCase
{
    use RefreshDatabase;

    private ScenarioLinkNodeHandler $handler;
    private ScenarioRun $run;
    private Scenario $targetScenario;
    private ScenarioVersion $targetVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(ScenarioLinkNodeHandler::class);

        $source = Scenario::query()->create(['name' => 'Source', 'is_active' => true]);
        $sourceVersion = ScenarioVersion::query()->create(['scenario_id' => $source->id]);
        $sourceRevision = $this->createRevision($sourceVersion, [
            'nodes_json' => [['id' => 'link', 'type' => 'scenario_link', 'data' => []]],
            'edges_json' => [],
        ]);

        $this->targetScenario = Scenario::query()->create(['name' => 'Target', 'is_active' => true]);
        $this->targetVersion = ScenarioVersion::query()->create(['scenario_id' => $this->targetScenario->id]);
        $this->createRevision($this->targetVersion, [
            'nodes_json' => [
                ['id' => 'target_start', 'type' => 'start', 'data' => []],
                ['id' => 'target_block', 'type' => 'block', 'data' => []],
            ],
            'edges_json' => [['id' => 'target-edge', 'source' => 'target_start', 'target' => 'target_block']],
        ]);

        $this->run = ScenarioRun::query()->create([
            'scenario_id' => $source->id,
            'scenario_version_id' => $sourceVersion->id,
            'scenario_version_revision_id' => $sourceRevision->id,
            'current_node_id' => 'link',
            'status' => 'active',
            'context' => ['preserved' => 'value'],
        ]);
    }

    public function test_is_never_interactive(): void
    {
        $this->assertFalse($this->handler->isInteractive($this->node()));
    }

    public function test_advance_enters_target_version_keeping_run_identity_and_pushes_call_frame(): void
    {
        $sourceScenarioId = $this->run->scenario_id;
        $sourceVersionId = $this->run->scenario_version_id;
        $sourceRevisionId = $this->run->scenario_version_revision_id;

        $result = $this->handler->advance($this->run, $this->node());
        $run = $this->run->fresh();

        $this->assertTrue($result->runMutated);
        $this->assertSame($sourceScenarioId, $run->scenario_id);
        $this->assertSame($this->targetVersion->id, $run->scenario_version_id);
        $this->assertSame('target_start', $run->current_node_id);
        $this->assertSame('value', $run->context['preserved'] ?? null);

        $stack = $run->context['_call_stack'] ?? [];
        $this->assertCount(1, $stack);
        $this->assertSame($sourceVersionId, $stack[0]['version_id']);
        $this->assertSame($sourceRevisionId, $stack[0]['revision_id']);
        $this->assertNull($stack[0]['return_node_id']);

        $this->assertDatabaseHas('scenario_run_steps', [
            'run_id' => $run->id,
            'node_id' => 'link',
            'node_type' => 'scenario_link',
        ]);
    }

    public function test_advance_uses_active_version_when_version_not_pinned(): void
    {
        $scenario = Scenario::query()->create(['name' => 'Pinless', 'is_active' => true]);

        $active = ScenarioVersion::query()->create(['scenario_id' => $scenario->id, 'status' => 'active']);
        $this->createRevision($active, [
            'nodes_json' => [['id' => 'a_start', 'type' => 'start', 'data' => []]],
            'edges_json' => [],
        ]);

        $draft = ScenarioVersion::query()->create(['scenario_id' => $scenario->id, 'status' => 'draft']);
        $this->createRevision($draft, [
            'nodes_json' => [['id' => 'd_start', 'type' => 'start', 'data' => []]],
            'edges_json' => [],
        ]);

        $node = [
            'id' => 'link',
            'type' => 'scenario_link',
            'data' => ['targetScenarioId' => $scenario->id, 'targetVersionId' => ''],
        ];

        $this->handler->advance($this->run, $node);
        $run = $this->run->fresh();

        $this->assertSame($active->id, $run->scenario_version_id);
        $this->assertSame('a_start', $run->current_node_id);
    }

    public function test_invalid_target_scenario_uuid_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('target scenario id');

        $node = $this->node();
        $node['data']['targetScenarioId'] = 'invalid';
        $this->handler->advance($this->run, $node);
    }

    public function test_invalid_target_version_uuid_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('target version id');

        $node = $this->node();
        $node['data']['targetVersionId'] = 'invalid';
        $this->handler->advance($this->run, $node);
    }

    public function test_version_must_belong_to_target_scenario(): void
    {
        $other = Scenario::query()->create(['name' => 'Other', 'is_active' => true]);
        $node = $this->node();
        $node['data']['targetScenarioId'] = $other->id;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not found');
        $this->handler->advance($this->run, $node);
    }

    /** @return array<string, mixed> */
    private function node(): array
    {
        return [
            'id' => 'link',
            'type' => 'scenario_link',
            'data' => [
                'targetScenarioId' => $this->targetScenario->id,
                'targetVersionId' => $this->targetVersion->id,
            ],
        ];
    }
}
