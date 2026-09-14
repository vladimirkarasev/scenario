<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Graph;

use Module\Scenario\Enums\ScenarioNodeType;
use RuntimeException;

final readonly class ScenarioGraphNavigator
{
    /** @return array<string, mixed> */
    public function startNode(ScenarioGraph $graph): array
    {
        foreach ($graph->nodes() as $node) {
            if (($node['type'] ?? null) === ScenarioNodeType::Start->value) {
                return $node;
            }
        }

        throw new RuntimeException('Scenario version does not contain a start node.');
    }

    /** @return array<string, mixed> */
    public function node(ScenarioGraph $graph, string $nodeId): array
    {
        foreach ($graph->nodes() as $node) {
            if (($node['id'] ?? null) === $nodeId) {
                return $node;
            }
        }

        throw new RuntimeException("Node {$nodeId} not found in scenario graph.");
    }

    /** @return list<array<string, mixed>> */
    public function outgoingEdges(ScenarioGraph $graph, string $nodeId): array
    {
        return array_values(array_filter(
            $graph->edges(),
            static fn(array $edge): bool => ($edge['source'] ?? null) === $nodeId,
        ));
    }

    public function defaultNextNodeId(ScenarioGraph $graph, string $nodeId): ?string
    {
        foreach ($this->outgoingEdges($graph, $nodeId) as $edge) {
            $target = $edge['target'] ?? null;

            if (is_string($target)) {
                return $target;
            }
        }

        return null;
    }
}
