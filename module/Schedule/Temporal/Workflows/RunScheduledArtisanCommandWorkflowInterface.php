<?php

declare(strict_types=1);

namespace Module\Schedule\Temporal\Workflows;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface RunScheduledArtisanCommandWorkflowInterface
{
    public const string WORKFLOW_TYPE = 'RunScheduledArtisanCommand';

    /** @return \Generator<int, mixed, mixed, mixed> */
    #[WorkflowMethod(name: self::WORKFLOW_TYPE)]
    public function run(string $command);
}
