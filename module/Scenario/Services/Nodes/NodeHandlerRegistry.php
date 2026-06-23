<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Scenario\Enums\ScenarioNodeType;

final readonly class NodeHandlerRegistry
{
    public function __construct(
        private BlockNodeHandler $block,
        private ActionNodeHandler $action,
        private ConditionNodeHandler $condition,
        private EndNodeHandler $end,
        private ScenarioLinkNodeHandler $scenarioLink,
        private DefaultNodeHandler $default,
    ) {}

    public function for(string $type): NodeHandlerInterface
    {
        return match ($type) {
            ScenarioNodeType::Block->value => $this->block,
            ScenarioNodeType::Action->value => $this->action,
            ScenarioNodeType::Condition->value => $this->condition,
            ScenarioNodeType::End->value => $this->end,
            ScenarioNodeType::ScenarioLink->value => $this->scenarioLink,
            default => $this->default,
        };
    }
}
