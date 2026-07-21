<?php

declare(strict_types=1);

namespace Module\Actions\Temporal;

interface RunActionsWorkflowStarterInterface
{
    public function startSequential(RunActionsWorkflowInput $input): void;

    public function startParallel(RunActionsParallelWorkflowInput $input): void;
}
