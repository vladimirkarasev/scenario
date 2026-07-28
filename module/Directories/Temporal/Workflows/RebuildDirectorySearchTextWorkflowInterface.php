<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Workflows;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface RebuildDirectorySearchTextWorkflowInterface
{
    public const string WORKFLOW_TYPE = 'RebuildDirectorySearchText';

    /** @return \Generator<int, mixed, mixed, mixed> */
    #[WorkflowMethod(name: self::WORKFLOW_TYPE)]
    public function run(int $directoryVersionId);
}
