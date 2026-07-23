<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Workflows;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface RunDirectorySyncScheduleWorkflowInterface
{
    public const string WORKFLOW_TYPE = 'RunDirectorySyncSchedule';

    /** @return \Generator<int, mixed, mixed, mixed> */
    #[WorkflowMethod(name: self::WORKFLOW_TYPE)]
    public function run(string $directoryId);
}
