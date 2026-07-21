<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'Actions.')]
interface ResumeScenarioRunActivityInterface
{
    /**
     * @param  array<string, mixed>  $actionContext
     * @return mixed
     */
    #[ActivityMethod(name: 'ResumeScenarioRun')]
    public function resume(string $runId, string $nodeId, bool $success, array $actionContext);
}
