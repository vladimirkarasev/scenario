<?php

declare(strict_types=1);

namespace Module\Directories\Temporal;

interface RunDirectoryImportWorkflowStarterInterface
{
    public function start(RunDirectoryImportWorkflowInput $input): void;

    public function startPaged(RunDirectoryImportWorkflowInput $input): void;
}
