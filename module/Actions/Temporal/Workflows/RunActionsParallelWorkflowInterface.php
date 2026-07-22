<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Workflows;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface RunActionsParallelWorkflowInterface
{
    /**
     * @param  list<string>              $beforeIds
     * @param  list<string>              $actionIds
     * @param  list<string>              $afterIds
     * @param  list<string>              $onErrorActionIds
     * @param  array<string, string>     $codeMap
     * @param  array<string, mixed>      $context
     * @param  array<string, list<int>>  $backoffByActionId
     * @param  array<string, int>        $delayBeforeByActionId
     * @return \Generator<int, mixed, mixed, array<string, mixed>>
     */
    #[WorkflowMethod(name: 'RunActionsParallel')]
    public function run(
        array $beforeIds,
        array $actionIds,
        array $afterIds,
        array $onErrorActionIds,
        array $codeMap,
        array $context,
        array $backoffByActionId,
        array $delayBeforeByActionId,
        ?string $scenarioRunId,
        ?string $actionNodeId = null,
    );
}
