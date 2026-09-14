<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Definition;

use Module\Scenario\Enums\ScenarioNodeType;
use Module\Scenario\Enums\ScenarioType;

final readonly class ScenarioNodeCatalog
{
    /** @return array{type: string, nodes: list<array{type: string, label: string, icon: string}>} */
    public function for(ScenarioType $type): array
    {
        return [
            'type' => $type->value,
            'nodes' => iterator_to_array($this->nodes($type), false),
        ];
    }

    /** @return iterable<array{type: string, label: string, icon: string}> */
    private function nodes(ScenarioType $type): iterable
    {
        foreach ($type->allowedNodeTypes() as $nodeType) {
            yield $this->node($nodeType);
        }
    }

    /** @return array{type: string, label: string, icon: string} */
    private function node(ScenarioNodeType $type): array
    {
        return [
            'type' => $type->value,
            'label' => $type->label(),
            'icon' => $type->icon(),
        ];
    }
}
