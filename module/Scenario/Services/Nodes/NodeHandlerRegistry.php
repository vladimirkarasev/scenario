<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Scenario\Enums\ScenarioNodeType;
use Module\Scenario\Services\Nodes\Action\ActionNodeHandler;
use Module\Scenario\Services\Nodes\Block\BlockNodeHandler;
use Module\Scenario\Services\Nodes\Condition\ConditionNodeHandler;
use Module\Scenario\Services\Nodes\End\EndNodeHandler;
use Module\Scenario\Services\Nodes\Question\QuestionNodeHandler;
use Module\Scenario\Services\Nodes\ScenarioLink\ScenarioLinkNodeHandler;

final readonly class NodeHandlerRegistry
{
    public function __construct(
        private BlockNodeHandler $block,
        private QuestionNodeHandler $question,
        private ActionNodeHandler $action,
        private ConditionNodeHandler $condition,
        private EndNodeHandler $end,
        private ScenarioLinkNodeHandler $scenarioLink,
        private DefaultNodeHandler $default,
    ) {
    }

    public function for(string $type): NodeHandlerInterface
    {
        return match ($type) {
            ScenarioNodeType::Block->value => $this->block,
            ScenarioNodeType::Question->value => $this->question,
            ScenarioNodeType::Action->value => $this->action,
            ScenarioNodeType::Condition->value => $this->condition,
            ScenarioNodeType::End->value => $this->end,
            ScenarioNodeType::ScenarioLink->value => $this->scenarioLink,
            default => $this->default,
        };
    }
}
