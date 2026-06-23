<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use Module\Scenario\Enums\ScenarioNodeType;
use Module\Scenario\Models\ScenarioVersion;
use RuntimeException;

final readonly class ScenarioGraphResolver
{
    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function snapshot(ScenarioVersion $version): array
    {
        $revision = $version->latestRevision;

        $nodes = $revision->nodes_json ?? [];
        $edges = $revision->edges_json ?? [];

        $this->validate($nodes, $edges);

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /** @return array<string, mixed> */
    public function findStartNode(ScenarioVersion $version): array
    {
        $node = collect($this->snapshot($version)['nodes'])
            ->first(fn (array $item): bool => ($item['type'] ?? null) === ScenarioNodeType::Start->value);

        if ($node === null) {
            throw new RuntimeException('Scenario version does not contain a start node.');
        }

        return $node;
    }

    /** @return array<string, mixed> */
    public function findNode(ScenarioVersion $version, string $nodeId): array
    {
        $node = collect($this->snapshot($version)['nodes'])
            ->first(fn (array $item): bool => ($item['id'] ?? null) === $nodeId);

        if ($node === null) {
            throw new RuntimeException("Node {$nodeId} not found in scenario graph.");
        }

        return $node;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function outgoingEdges(ScenarioVersion $version, string $nodeId): array
    {
        return collect($this->snapshot($version)['edges'])
            ->filter(fn (array $edge): bool => ($edge['source'] ?? null) === $nodeId)
            ->values()
            ->all();
    }

    public function defaultNextNodeId(ScenarioVersion $version, string $nodeId): ?string
    {
        return collect($this->outgoingEdges($version, $nodeId))
            ->map(fn (array $edge): ?string => is_string($edge['target']) ? $edge['target'] : null)
            ->filter()
            ->first();
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, array<string, mixed>> $edges
     */
    private function validate(array $nodes, array $edges): void
    {
        validator(
            ['nodes' => $nodes, 'edges' => $edges],
            [
                'nodes' => ['required', 'array', 'min:1'],
                'nodes.*.id' => ['required', 'string'],
                'nodes.*.type' => ['required', 'string'],
                'nodes.*.data' => ['nullable', 'array'],
                'edges' => ['required', 'array'],
                'edges.*.id' => ['required', 'string'],
                'edges.*.source' => ['required', 'string'],
                'edges.*.target' => ['required', 'string'],
            ],
        )->validate();

        $nodeIds = collect($nodes)->pluck('id')->all();

        foreach ($nodes as $node) {
            $nodeType = $node['type'];
            if (! is_string($nodeType)) {
                throw new RuntimeException('Unsupported node type: type must be a string.');
            }
            if (! in_array($nodeType, ScenarioNodeType::values(), true)) {
                throw new RuntimeException(sprintf('Unsupported node type `%s`.', $nodeType));
            }
        }

        foreach ($edges as $edge) {
            if (! in_array($edge['source'], $nodeIds, true) || ! in_array($edge['target'], $nodeIds, true)) {
                throw new RuntimeException('Scenario graph contains edge with unknown source or target node.');
            }
        }
    }
}
