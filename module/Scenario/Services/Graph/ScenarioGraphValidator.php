<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Graph;

use Illuminate\Contracts\Validation\Factory;
use Module\Scenario\Enums\ScenarioNodeType;
use RuntimeException;

final readonly class ScenarioGraphValidator
{
    public function __construct(
        private Factory $validation,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     */
    public function validate(array $nodes, array $edges): void
    {
        $this->validation->make(
            ['nodes' => $nodes, 'edges' => $edges],
            [
                'nodes' => ['required', 'array', 'min:1'],
                'nodes.*.id' => ['required', 'string'],
                'nodes.*.type' => ['required', 'string'],
                'nodes.*.data' => ['nullable', 'array'],
                'edges' => ['present', 'array'],
                'edges.*.id' => ['required', 'string'],
                'edges.*.source' => ['required', 'string'],
                'edges.*.target' => ['required', 'string'],
            ],
        )->validate();

        $nodeIds = array_column($nodes, 'id');

        foreach ($nodes as $node) {
            $nodeType = $node['type'];

            if (!is_string($nodeType)) {
                throw new RuntimeException('Unsupported node type: type must be a string.');
            }

            if (!in_array($nodeType, ScenarioNodeType::values(), true)) {
                throw new RuntimeException(sprintf('Unsupported node type `%s`.', $nodeType));
            }
        }

        foreach ($edges as $edge) {
            if (!in_array($edge['source'], $nodeIds, true) || !in_array($edge['target'], $nodeIds, true)) {
                throw new RuntimeException('Scenario graph contains edge with unknown source or target node.');
            }
        }
    }
}
