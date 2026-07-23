<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Workflows;

use Module\Directories\Temporal\Activities\RunDirectorySyncScheduleActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;

final class RunDirectorySyncScheduleWorkflow implements RunDirectorySyncScheduleWorkflowInterface
{
    /** @return \Generator<int, mixed, mixed, mixed> */
    public function run(string $directoryId)
    {
        $activity = Workflow::newActivityStub(
            RunDirectorySyncScheduleActivityInterface::class,
            ActivityOptions::new()->withStartToCloseTimeout(30),
        );

        yield $activity->run($directoryId);
    }
}
