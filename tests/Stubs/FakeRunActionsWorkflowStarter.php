<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Module\Actions\Temporal\RunActionsParallelWorkflowInput;
use Module\Actions\Temporal\RunActionsWorkflowInput;
use Module\Actions\Temporal\RunActionsWorkflowStarterInterface;

final class FakeRunActionsWorkflowStarter implements RunActionsWorkflowStarterInterface
{
    /** @var list<RunActionsWorkflowInput> */
    public array $sequentialCalls = [];

    /** @var list<RunActionsParallelWorkflowInput> */
    public array $parallelCalls = [];

    public function startSequential(RunActionsWorkflowInput $input): void
    {
        $this->sequentialCalls[] = $input;
    }

    public function startParallel(RunActionsParallelWorkflowInput $input): void
    {
        $this->parallelCalls[] = $input;
    }
}
