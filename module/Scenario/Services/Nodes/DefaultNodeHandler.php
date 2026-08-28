<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Graph\ScenarioGraphResolver;
use Module\Scenario\Services\Runtime\ScenarioRunVersionResolver;
use Module\Scenario\Services\Variables\VariableResolver;

final readonly class DefaultNodeHandler implements NodeHandlerInterface
{
    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private VariableResolver $variableResolver,
        private NodeDataReader $nodeData,
        private ScenarioRunVersionResolver $runVersions,
    ) {
    }

    public function isInteractive(array $node): bool
    {
        return false;
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        return NodeAdvanceResult::next(
            $this->graphResolver->defaultNextNodeId($this->runVersions->resolve($run), $this->nodeData->id($node)),
        );
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        return $this->graphResolver->defaultNextNodeId($this->runVersions->resolve($run), $this->nodeData->id($node));
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        return [
            'type' => $this->nodeData->type($node),
            'data' => $this->variableResolver->resolve($this->nodeData->data($node), $context),
        ];
    }
}
