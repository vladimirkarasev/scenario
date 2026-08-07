<?php

declare(strict_types=1);

namespace Module\Directories\Temporal;

interface RebuildDirectorySearchTextWorkflowStarterInterface
{
    public function start(int $directoryVersionId): void;
}
