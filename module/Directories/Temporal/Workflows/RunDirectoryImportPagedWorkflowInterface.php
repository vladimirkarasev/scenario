<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Workflows;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface RunDirectoryImportPagedWorkflowInterface
{
    /**
     * @return \Generator<int, mixed, mixed, void>
     */
    #[WorkflowMethod(name: 'RunDirectoryImportPaged')]
    public function run(int $directoryImportId);
}
