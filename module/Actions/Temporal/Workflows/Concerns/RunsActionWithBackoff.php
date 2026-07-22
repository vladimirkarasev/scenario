<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Workflows\Concerns;

use Module\Actions\Temporal\Activities\ExecuteActionActivityInterface;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Workflow;

trait RunsActionWithBackoff
{
    /**
     * @param  ExecuteActionActivityInterface  $activity  прокси Workflow::newActivityStub(), не implements интерфейс в рантайме
     * @param  array<string, mixed>  $context
     * @param  list<int>  $backoff
     * @return \Generator<int, mixed, mixed, array{status: string, output: array<string, mixed>, error: string|null}>
     */
    private function runActionWithBackoff(
        object $activity,
        string $actionId,
        string $code,
        array $context,
        ?string $scenarioRunId,
        int $delayBefore,
        array $backoff,
        ?string $actionNodeId = null,
    ): \Generator {
        if ($delayBefore > 0) {
            yield Workflow::timer($delayBefore);
        }

        $attempts = $backoff !== [] ? $backoff : [0];
        $lastIndex = array_key_last($attempts);

        foreach ($attempts as $index => $delay) {
            if ($delay > 0) {
                yield Workflow::timer($delay);
            }

            try {
                /** @var array{status: string, output: array<string, mixed>, error: string|null} $result */
                $result = yield $activity->execute($actionId, $context, $code, $scenarioRunId, $index + 1, $actionNodeId);

                return $result;
            } catch (ActivityFailure $exception) {
                if ($index === $lastIndex) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('unreachable');
    }
}
