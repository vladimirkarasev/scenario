<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Workflows;

use Module\Directories\Temporal\Activities\RebuildDirectorySearchTextActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;

final class RebuildDirectorySearchTextWorkflow implements RebuildDirectorySearchTextWorkflowInterface
{
    /** @return \Generator<int, mixed, mixed, mixed> */
    public function run(int $directoryVersionId)
    {
        $activity = Workflow::newActivityStub(
            RebuildDirectorySearchTextActivityInterface::class,
            ActivityOptions::new()->withStartToCloseTimeout(300),
        );

        yield $activity->run($directoryVersionId);
    }
}
