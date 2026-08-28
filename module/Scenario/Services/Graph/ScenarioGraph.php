<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Graph;

final readonly class ScenarioGraph
{
    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     */
    public function __construct(
        private array $nodes,
        private array $edges,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /** @return list<array<string, mixed>> */
    public function edges(): array
    {
        return $this->edges;
    }

    /** @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['nodes' => $this->nodes, 'edges' => $this->edges];
    }
}
