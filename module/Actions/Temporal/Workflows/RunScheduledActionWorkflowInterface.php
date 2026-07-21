<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Workflows;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface RunScheduledActionWorkflowInterface
{
    public const string WORKFLOW_TYPE = 'RunScheduledAction';

    /** @return \Generator<int, mixed, mixed, mixed> */
    #[WorkflowMethod(name: self::WORKFLOW_TYPE)]
    public function run(int $scheduleId);
}
