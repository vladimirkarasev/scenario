<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\End;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeDataReader;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;
use Module\Scenario\Services\Variables\VariableResolver;

final readonly class EndNodeHandler implements NodeHandlerInterface
{
    public function __construct(
        private VariableResolver $variableResolver,
        private NodeDataReader $nodeData,
    ) {
    }

    public function isInteractive(array $node): bool
    {
        return true;
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        return NodeAdvanceResult::next(null);
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        return null;
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        $data = $this->nodeData->data($node);

        return [
            'type' => 'end',
            'title' => $this->variableResolver->resolve(
                $this->nodeData->string($data, 'title', 'Все шаги успешно пройдены'),
                $context
            ),
            'hideTitle' => $this->nodeData->boolean($data, 'hideTitle', true),
            'description' => $this->variableResolver->resolve($this->nodeData->string($data, 'description', ''), $context),
            'blocks' => $this->variableResolver->resolve($this->nodeData->array($data, 'blocks'), $context),
        ];
    }
}
