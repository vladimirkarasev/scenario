<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Graph;

use Module\Scenario\Models\ScenarioVersion;

final readonly class ScenarioGraphResolver
{
    public function __construct(
        private ScenarioGraphFactory $factory,
        private ScenarioGraphNavigator $navigator,
    ) {
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function snapshot(ScenarioVersion $version): array
    {
        return $this->graph($version)->toArray();
    }

    /** @return array<string, mixed> */
    public function findStartNode(ScenarioVersion $version): array
    {
        return $this->navigator->startNode($this->graph($version));
    }

    /** @return array<string, mixed> */
    public function findNode(ScenarioVersion $version, string $nodeId): array
    {
        return $this->navigator->node($this->graph($version), $nodeId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function outgoingEdges(ScenarioVersion $version, string $nodeId): array
    {
        return $this->navigator->outgoingEdges($this->graph($version), $nodeId);
    }

    public function defaultNextNodeId(ScenarioVersion $version, string $nodeId): ?string
    {
        return $this->navigator->defaultNextNodeId($this->graph($version), $nodeId);
    }

    /**
     * Создаёт проверенный снимок графа и скрывает формат хранения ревизии от клиентов.
     */
    private function graph(ScenarioVersion $version): ScenarioGraph
    {
        $revision = $version->latestRevision;

        return $this->factory->create(
            $revision->nodes_json ?? [],
            $revision->edges_json ?? [],
        );
    }
}
