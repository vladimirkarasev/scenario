<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'Actions.')]
interface ExecuteActionActivityInterface
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{status: string, output: array<string, mixed>, error: string|null}
     */
    #[ActivityMethod(name: 'ExecuteAction')]
    public function execute(string $actionId, array $context, string $code, ?string $scenarioRunId, int $attemptNumber = 1, ?string $actionNodeId = null): array;
}
