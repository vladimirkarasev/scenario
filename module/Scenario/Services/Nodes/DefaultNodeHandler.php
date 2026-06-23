<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\ScenarioGraphResolver;
use Module\Scenario\Services\VariableResolver;

final readonly class DefaultNodeHandler implements NodeHandlerInterface
{
    use NodeHelpers;

    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private VariableResolver $variableResolver,
    ) {}

    public function isInteractive(array $node): bool
    {
        return false;
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        return NodeAdvanceResult::next(
            $this->graphResolver->defaultNextNodeId($this->runVersion($run), $this->nodeId($node)),
        );
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        return $this->graphResolver->defaultNextNodeId($this->runVersion($run), $this->nodeId($node));
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        return [
            'type' => $this->nodeType($node),
            'data' => $this->variableResolver->resolve($this->nodeData($node), $context),
        ];
    }
}
