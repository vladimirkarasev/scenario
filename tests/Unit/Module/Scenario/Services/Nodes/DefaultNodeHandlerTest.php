<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Nodes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\DefaultNodeHandler;
use Tests\TestCase;

final class DefaultNodeHandlerTest extends TestCase
{
    use RefreshDatabase;

    private DefaultNodeHandler $handler;

    private ScenarioVersion $version;

    private ScenarioRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = app(DefaultNodeHandler::class);

        $scenario = Scenario::query()->create(['name' => 'Test', 'is_active' => true]);
        $this->version = ScenarioVersion::query()->create(['scenario_id' => $scenario->id]);
        $revision = $this->createRevision($this->version, [
            'nodes_json' => [
                ['id' => 'node_start', 'type' => 'start', 'data' => ['key' => 'value']],
                ['id' => 'node_next', 'type' => 'block', 'data' => []],
                ['id' => 'node_end', 'type' => 'end', 'data' => []],
            ],
            'edges_json' => [
                ['id' => 'e1', 'source' => 'node_start', 'target' => 'node_next'],
                ['id' => 'e2', 'source' => 'node_next', 'target' => 'node_end'],
            ],
        ]);

        $this->run = ScenarioRun::query()->create([
            'scenario_id' => $scenario->id,
            'scenario_version_id' => $this->version->id,
            'scenario_version_revision_id' => $revision->id,
            'current_node_id' => 'node_start',
            'status' => 'active',
            'context' => [],
        ]);
    }

    public function test_is_never_interactive(): void
    {
        $this->assertFalse($this->handler->isInteractive(['id' => 'node_start', 'type' => 'start', 'data' => []]));
    }

    public function test_advance_returns_next_node(): void
    {
        $node = ['id' => 'node_start', 'type' => 'start', 'data' => []];

        $result = $this->handler->advance($this->run, $node);

        $this->assertSame('node_next', $result->nextNodeId);
    }

    public function test_continue_from_returns_next_node(): void
    {
        $node = ['id' => 'node_start', 'type' => 'start', 'data' => []];

        $result = $this->handler->continueFrom(
            $this->run,
            $node,
            new ScenarioRunContinueData(input: [], selectedTargetNodeId: null),
        );

        $this->assertSame('node_next', $result);
    }

    public function test_render_returns_type_and_data(): void
    {
        $node = ['id' => 'node_start', 'type' => 'start', 'data' => ['color' => 'blue', 'size' => 10]];

        $result = $this->handler->render($this->version, $node, []);

        $this->assertSame('start', $result['type']);
        $this->assertSame('blue', $result['data']['color'] ?? null);
    }

    public function test_render_resolves_templates_in_data(): void
    {
        $node = [
            'id' => 'node_start',
            'type' => 'start',
            'data' => ['message' => 'Hello {{ user }}'],
        ];

        $result = $this->handler->render($this->version, $node, ['user' => 'Bob']);

        $this->assertSame('Hello Bob', $result['data']['message'] ?? null);
    }
}
