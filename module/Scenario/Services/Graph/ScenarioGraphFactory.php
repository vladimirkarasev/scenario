<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Graph;

final readonly class ScenarioGraphFactory
{
    public function __construct(
        private ScenarioGraphValidator $validator,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     */
    public function create(array $nodes, array $edges): ScenarioGraph
    {
        $this->validator->validate($nodes, $edges);

        return new ScenarioGraph(array_values($nodes), array_values($edges));
    }
}
