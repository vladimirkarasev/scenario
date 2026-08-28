<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Scenario\Services\Graph;

use Illuminate\Validation\ValidationException;
use Module\Scenario\Services\Graph\ScenarioGraphFactory;
use Module\Scenario\Services\Graph\ScenarioGraphNavigator;
use RuntimeException;
use Tests\TestCase;

final class ScenarioGraphFactoryTest extends TestCase
{
    private ScenarioGraphFactory $factory;

    private ScenarioGraphNavigator $navigator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = app(ScenarioGraphFactory::class);
        $this->navigator = app(ScenarioGraphNavigator::class);
    }

    public function test_creates_valid_graph_for_navigation(): void
    {
        $graph = $this->factory->create(
            [
                ['id' => 'start', 'type' => 'start', 'data' => []],
                ['id' => 'end', 'type' => 'end', 'data' => []],
            ],
            [
                ['id' => 'edge', 'source' => 'start', 'target' => 'end'],
            ],
        );

        $this->assertSame('start', $this->navigator->startNode($graph)['id']);
        $this->assertSame('end', $this->navigator->node($graph, 'end')['id']);
        $this->assertSame('end', $this->navigator->defaultNextNodeId($graph, 'start'));
    }

    public function test_rejects_invalid_snapshot_shape(): void
    {
        $this->expectException(ValidationException::class);

        $this->factory->create([], []);
    }

    public function test_rejects_unsupported_node_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported node type `unknown`.');

        $this->factory->create(
            [['id' => 'node', 'type' => 'unknown', 'data' => []]],
            [],
        );
    }

    public function test_rejects_edge_with_unknown_target(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unknown source or target node');

        $this->factory->create(
            [['id' => 'start', 'type' => 'start', 'data' => []]],
            [['id' => 'edge', 'source' => 'start', 'target' => 'missing']],
        );
    }
}
