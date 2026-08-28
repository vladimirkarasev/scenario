<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Action;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeDataReader;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;
use Module\Scenario\Services\Nodes\RetryableNodeHandler;
use Module\Scenario\Services\Graph\ScenarioGraphResolver;
use Module\Scenario\Services\Runtime\ScenarioRunVersionResolver;
use Module\Scenario\Services\Variables\VariableResolver;

final readonly class ActionNodeHandler implements NodeHandlerInterface, RetryableNodeHandler
{
    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private VariableResolver $variableResolver,
        private ActionNodePipeline $pipeline,
        private NodeDataReader $nodeData,
        private ScenarioRunVersionResolver $runVersions,
    ) {
    }

    public function isInteractive(array $node): bool
    {
        if ($this->nodeData->boolean($this->nodeData->data($node), 'wait_for_result')) {
            return false;
        }

        return !$this->nodeData->boolean($this->nodeData->data($node), 'skipInSurvey');
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        $nodeId = $this->nodeData->id($node);

        if (!$this->nodeData->boolean($this->nodeData->data($node), 'wait_for_result')) {
            $this->pipeline->dispatch($run, $node);

            return NodeAdvanceResult::next($this->nextNodeId($run, $nodeId));
        }

        $state = $this->pipeline->state($this->runContext($run), $nodeId);

        if ($state === ActionStatus::Done) {
            return NodeAdvanceResult::next($this->nextNodeId($run, $nodeId));
        }

        if ($state === null) {
            $this->pipeline->markState($run, $nodeId, ActionStatus::Running);

            if (!$this->pipeline->dispatch($run, $node, $nodeId)) {
                $this->pipeline->markState($run, $nodeId, ActionStatus::Done);

                return NodeAdvanceResult::next($this->nextNodeId($run, $nodeId));
            }
        }

        return NodeAdvanceResult::pause();
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        $nodeId = $this->nodeData->id($node);

        if ($this->nodeData->boolean($this->nodeData->data($node), 'wait_for_result')) {
            $this->pipeline->markState($run, $nodeId, ActionStatus::Done);

            return $this->nextNodeId($run, $nodeId);
        }

        $this->pipeline->dispatch($run, $node);

        return $this->nextNodeId($run, $nodeId);
    }

    public function retry(ScenarioRun $run, array $node): bool
    {
        return $this->pipeline->retry($run, $node);
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        $data = $this->nodeData->data($node);
        $data['hideTitle'] = $this->nodeData->boolean($data, 'hideTitle', true);
        $nodeId = $this->nodeData->id($node);

        $results = $this->pipeline->stageResults($context, $nodeId);
        $failed = in_array(ActionStatus::Failed->value, $results, true)
            || $this->pipeline->state($context, $nodeId) === ActionStatus::Failed;

        return [
            'type' => $this->nodeData->type($node),
            'data' => $this->variableResolver->resolve($data, $context),
            'wait_for_result' => $this->nodeData->boolean($data, 'wait_for_result'),
            'stages' => $this->pipeline->stages($node),
            'results' => $results,
            'failed' => $failed,
        ];
    }

    private function nextNodeId(ScenarioRun $run, string $nodeId): ?string
    {
        return $this->graphResolver->defaultNextNodeId($this->runVersions->resolve($run), $nodeId);
    }

    /** @return array<string, mixed> */
    private function runContext(ScenarioRun $run): array
    {
        return is_array($run->context) ? $run->context : [];
    }
}
