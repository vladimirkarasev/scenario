<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Workflows;

use Module\Actions\Temporal\Activities\ExecuteActionActivityInterface;
use Module\Actions\Temporal\Workflows\Concerns\RunsActionWithBackoff;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Promise;
use Temporal\Workflow;

final class RunActionsParallelWorkflow implements RunActionsParallelWorkflowInterface
{
    use RunsActionWithBackoff;

    /**
     * @param  list<string>              $beforeIds
     * @param  list<string>              $actionIds
     * @param  list<string>              $afterIds
     * @param  list<string>              $onErrorActionIds
     * @param  array<string, string>     $codeMap
     * @param  array<string, mixed>      $context
     * @param  array<string, list<int>>  $backoffByActionId
     * @param  array<string, int>        $delayBeforeByActionId
     * @return \Generator<int, mixed, mixed, array<string, mixed>>
     */
    public function run(
        array $beforeIds,
        array $actionIds,
        array $afterIds,
        array $onErrorActionIds,
        array $codeMap,
        array $context,
        array $backoffByActionId,
        array $delayBeforeByActionId,
        ?string $scenarioRunId,
        ?string $actionNodeId = null,
    ) {
        $activity = Workflow::newActivityStub(
            ExecuteActionActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(60)
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1)),
        );

        $beforeSucceeded = yield from $this->runIndependently(
            $activity,
            $beforeIds,
            $codeMap,
            $backoffByActionId,
            $delayBeforeByActionId,
            $context,
            $scenarioRunId,
            $actionNodeId,
        );

        if (!$beforeSucceeded || $actionIds === []) {
            return $context;
        }

        $promises = [];

        foreach ($actionIds as $actionId) {
            $code = $codeMap[$actionId] ?? $actionId;
            $promises[] = Workflow::async(
                fn () => yield from $this->runActionWithBackoff(
                    $activity,
                    $actionId,
                    $code,
                    $context,
                    $scenarioRunId,
                    $delayBeforeByActionId[$actionId] ?? 0,
                    $backoffByActionId[$actionId] ?? [],
                    $actionNodeId,
                ),
            );
        }

        try {
            yield Promise::all($promises);
            $nextIds = $afterIds;
        } catch (ActivityFailure) {
            $nextIds = $onErrorActionIds;
        }

        yield from $this->runIndependently(
            $activity,
            $nextIds,
            $codeMap,
            $backoffByActionId,
            $delayBeforeByActionId,
            $context,
            $scenarioRunId,
            $actionNodeId,
        );

        return $context;
    }

    /**
     * @param  ExecuteActionActivityInterface  $activity  прокси Workflow::newActivityStub(), не implements интерфейс в рантайме
     * @param  list<string>              $actionIds
     * @param  array<string, string>     $codeMap
     * @param  array<string, list<int>>  $backoffByActionId
     * @param  array<string, int>        $delayBeforeByActionId
     * @param  array<string, mixed>      $context
     * @return \Generator<int, mixed, mixed, bool>
     */
    private function runIndependently(
        object $activity,
        array $actionIds,
        array $codeMap,
        array $backoffByActionId,
        array $delayBeforeByActionId,
        array $context,
        ?string $scenarioRunId,
        ?string $actionNodeId = null,
    ) {
        foreach ($actionIds as $actionId) {
            $code = $codeMap[$actionId] ?? $actionId;

            try {
                yield from $this->runActionWithBackoff(
                    $activity,
                    $actionId,
                    $code,
                    $context,
                    $scenarioRunId,
                    $delayBeforeByActionId[$actionId] ?? 0,
                    $backoffByActionId[$actionId] ?? [],
                    $actionNodeId,
                );
            } catch (ActivityFailure) {
                return false;
            }
        }

        return true;
    }
}
