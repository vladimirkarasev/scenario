<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Workflows;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface RunDirectoryImportWorkflowInterface
{
    /**
     * @return \Generator<int, mixed, mixed, void>
     */
    #[WorkflowMethod(name: 'RunDirectoryImport')]
    public function run(int $directoryImportId);
}
