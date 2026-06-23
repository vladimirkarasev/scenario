<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Module\Scenario\Enums\ScenarioNodeType;

final readonly class ScenarioVersionData
{
    /**
     * @param array<mixed>                     $schemaJson
     * @param array<int, array<string, mixed>> $nodesJson
     * @param array<int, array<string, mixed>> $edgesJson
     * @param array<int, array<string, mixed>> $inputFields
     */
    public function __construct(
        public array $schemaJson,
        public array $nodesJson,
        public array $edgesJson,
        public int $schemaVersion,
        public array $inputFields = [],
        public ?string $name = null,
        public bool $hasName = false,
        public ?string $status = null,
        public bool $hasStatus = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $schemaJson = (array) $request->input('schema_json', []);
        $snapshot = self::normalizeSnapshot($schemaJson);

        return new self(
            schemaJson: $schemaJson,
            nodesJson: $snapshot['nodes_json'],
            edgesJson: $snapshot['edges_json'],
            schemaVersion: $snapshot['schema_version'],
            inputFields: self::normalizeInputFields($request->input('input_fields')),
            name: $request->filled('name') ? trim($request->string('name')->toString()) : null,
            hasName: $request->has('name'),
            status: $request->filled('status') ? $request->string('status')->toString() : null,
            hasStatus: $request->has('status'),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private static function normalizeInputFields(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $result = [];
        foreach (array_values($raw) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $result[] = [
                'key' => is_scalar($item['key'] ?? null) ? (string) $item['key'] : '',
                'label' => is_scalar($item['label'] ?? null) ? (string) $item['label'] : '',
                'type' => is_scalar($item['type'] ?? null) ? (string) $item['type'] : 'text',
            ];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        $attributes = [];

        if ($this->hasName) {
            $attributes['name'] = $this->name;
        }

        if ($this->hasStatus) {
            $attributes['status'] = $this->status ?? 'draft';
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    public function toRevisionAttributes(): array
    {
        return [
            'schema_json' => $this->schemaJson,
            'nodes_json' => $this->nodesJson,
            'edges_json' => $this->edgesJson,
            'input_fields' => $this->inputFields,
            'schema_version' => $this->schemaVersion,
        ];
    }

    /**
     * @param  array<array-key, mixed>                                                                                                $schemaJson
     * @return array{nodes_json: array<int, array<string, mixed>>, edges_json: array<int, array<string, mixed>>, schema_version: int}
     */
    private static function normalizeSnapshot(array $schemaJson): array
    {
        if (isset($schemaJson['nodes'], $schemaJson['edges']) && is_array($schemaJson['nodes']) && is_array($schemaJson['edges'])) {
            /** @var array<int, array<string, mixed>> $nodes */
            $nodes = array_values($schemaJson['nodes']);
            /** @var array<int, array<string, mixed>> $edges */
            $edges = array_values($schemaJson['edges']);

            self::validateNodes($nodes);
            self::validateEdges($edges);

            $rawVersion = $schemaJson['schema_version'] ?? 1;

            return [
                'nodes_json' => $nodes,
                'edges_json' => $edges,
                'schema_version' => is_numeric($rawVersion) ? (int) $rawVersion : 1,
            ];
        }

        $blocks = is_array($schemaJson['blocks'] ?? null) ? $schemaJson['blocks'] : [];
        $connections = is_array($schemaJson['connections'] ?? null) ? $schemaJson['connections'] : [];

        /** @var array<int, array<string, mixed>> $nodes */
        $nodes = array_map(static function (mixed $block): array {
            $block = is_array($block) ? $block : [];

            return [
                'id' => is_scalar($block['id'] ?? null) ? (string) $block['id'] : '',
                'type' => is_scalar($block['type'] ?? null) ? (string) $block['type'] : ScenarioNodeType::Block->value,
                'data' => is_array($block['data'] ?? null) ? $block['data'] : [],
                'position' => is_array($block['position'] ?? null) ? $block['position'] : ['x' => 0, 'y' => 0],
            ];
        }, $blocks);

        /** @var array<int, array<string, mixed>> $edges */
        $edges = array_map(static function (mixed $connection): array {
            $connection = is_array($connection) ? $connection : [];
            $sourceBlockId = data_get($connection, 'source.blockId', '');
            $targetBlockId = data_get($connection, 'target.blockId', '');

            return [
                'id' => is_scalar($connection['id'] ?? null) ? (string) $connection['id'] : '',
                'source' => is_scalar($sourceBlockId) ? (string) $sourceBlockId : '',
                'target' => is_scalar($targetBlockId) ? (string) $targetBlockId : '',
                'sourceHandle' => data_get($connection, 'source.port'),
                'targetHandle' => data_get($connection, 'target.port'),
                'label' => data_get($connection, 'label'),
                'data' => is_array($connection['data'] ?? null) ? $connection['data'] : [],
            ];
        }, $connections);

        self::validateNodes($nodes);
        self::validateEdges($edges);

        $rawVersion = $schemaJson['version'] ?? 1;

        return [
            'nodes_json' => array_values($nodes),
            'edges_json' => array_values($edges),
            'schema_version' => is_numeric($rawVersion) ? (int) $rawVersion : 1,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    private static function validateNodes(array $nodes): void
    {
        validator(
            ['nodes' => $nodes],
            [
                'nodes' => ['array'],
                'nodes.*.id' => ['required', 'string'],
                'nodes.*.type' => ['required', Rule::in(ScenarioNodeType::values())],
                'nodes.*.data' => ['nullable', 'array'],
            ],
        )->validate();
    }

    /**
     * @param array<int, array<string, mixed>> $edges
     */
    private static function validateEdges(array $edges): void
    {
        validator(
            ['edges' => $edges],
            [
                'edges' => ['array'],
                'edges.*.id' => ['required', 'string'],
                'edges.*.source' => ['required', 'string'],
                'edges.*.target' => ['required', 'string'],
                'edges.*.sourceHandle' => ['nullable'],
                'edges.*.targetHandle' => ['nullable'],
                'edges.*.label' => ['nullable'],
                'edges.*.data' => ['nullable', 'array'],
            ],
        )->validate();
    }
}
