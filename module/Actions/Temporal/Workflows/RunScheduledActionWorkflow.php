<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Workflows;

use Module\Actions\Temporal\Activities\RunScheduledActionActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;

final class RunScheduledActionWorkflow implements RunScheduledActionWorkflowInterface
{
    /** @return \Generator<int, mixed, mixed, mixed> */
    public function run(int $scheduleId)
    {
        $activity = Workflow::newActivityStub(
            RunScheduledActionActivityInterface::class,
            ActivityOptions::new()->withStartToCloseTimeout(30),
        );

        yield $activity->run($scheduleId);
    }
}
