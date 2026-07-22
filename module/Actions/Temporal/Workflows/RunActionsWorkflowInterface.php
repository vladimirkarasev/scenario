<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Workflows;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface RunActionsWorkflowInterface
{
    /**
     * @param  list<string>              $actionIds
     * @param  list<string>              $onErrorActionIds
     * @param  array<string, string>     $codeMap
     * @param  array<string, mixed>      $context
     * @param  array<string, string>     $scopeMap
     * @param  array<string, list<int>>  $backoffByActionId
     * @param  array<string, int>        $delayBeforeByActionId
     * @return \Generator<int, mixed, mixed, array<string, mixed>>
     */
    #[WorkflowMethod(name: 'RunActions')]
    public function run(
        array $actionIds,
        array $onErrorActionIds,
        array $codeMap,
        array $context,
        array $scopeMap,
        array $backoffByActionId,
        array $delayBeforeByActionId,
        ?string $scenarioRunId,
        ?string $scenarioNodeId,
        ?string $actionNodeId = null,
    );
}
