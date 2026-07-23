<?php

declare(strict_types=1);

namespace Module\Schedule\Temporal\Workflows;

use Generator;
use Module\Schedule\Temporal\Activities\RunScheduledArtisanCommandActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;

final class RunScheduledArtisanCommandWorkflow implements RunScheduledArtisanCommandWorkflowInterface
{
    /** @return Generator<int, mixed, mixed, mixed> */
    public function run(string $command): Generator
    {
        $activity = Workflow::newActivityStub(
            RunScheduledArtisanCommandActivityInterface::class,
            ActivityOptions::new()->withStartToCloseTimeout(120),
        );

        yield $activity->run($command);
    }
}
