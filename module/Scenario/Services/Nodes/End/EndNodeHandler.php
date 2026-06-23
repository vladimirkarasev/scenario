<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\End;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;
use Module\Scenario\Services\Nodes\NodeHelpers;
use Module\Scenario\Services\VariableResolver;

final readonly class EndNodeHandler implements NodeHandlerInterface
{
    use NodeHelpers;

    public function __construct(
        private VariableResolver $variableResolver,
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
        $data = $this->nodeData($node);

        return [
            'type' => 'end',
            'title' => $this->variableResolver->resolve(
                $this->strField($data, 'title', 'Все шаги успешно пройдены'),
                $context
            ),
            'description' => $this->variableResolver->resolve($this->strField($data, 'description', ''), $context),
            'blocks' => $this->variableResolver->resolve($this->arrayField($data, 'blocks'), $context),
        ];
    }
}
