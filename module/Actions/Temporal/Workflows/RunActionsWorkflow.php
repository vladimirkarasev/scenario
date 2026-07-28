<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Workflows;

use Module\Actions\Temporal\Activities\ExecuteActionActivityInterface;
use Module\Actions\Temporal\Activities\ResumeScenarioRunActivityInterface;
use Module\Actions\Temporal\Workflows\Concerns\RunsActionWithBackoff;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Workflow;

final class RunActionsWorkflow implements RunActionsWorkflowInterface
{
    use RunsActionWithBackoff;

    /**
     * @param  list<string>              $actionIds
     * @param  list<string>              $onErrorActionIds
     * @param  array<string, string>     $codeMap
     * @param  array<string, mixed>      $context
     * @param  array<string, string>     $scopeMap
     * @param  array<string, list<int>>  $backoffByActionId
     * @param  array<string, int>        $delayBeforeByActionId
     * @return \Generator<int, mixed, mixed, array<string, mixed>>
     */
    public function run(
        array $actionIds,
        array $onErrorActionIds,
        array $codeMap,
        array $context,
        array $scopeMap,
        array $backoffByActionId,
        array $delayBeforeByActionId,
        ?string $scenarioRunId,
        ?string $scenarioNodeId,
        ?string $actionNodeId = null,
    ) {
        Workflow::setCurrentDetails($this->chainStartedDetails(count($actionIds)));

        $activity = Workflow::newActivityStub(
            ExecuteActionActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(60)
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1)),
        );

        $chain = yield from $this->runChain(
            $activity,
            $actionIds,
            $codeMap,
            $scopeMap,
            $backoffByActionId,
            $delayBeforeByActionId,
            $context,
            $scenarioRunId,
            $actionNodeId,
        );
        $context = $chain['context'];
        $success = $chain['failedActionId'] === null;

        if (!$success) {
            $context = [
                ...$context,
                'failed_action_id' => $chain['failedActionId'],
                'failed_error' => $chain['failedError'],
            ];

            Workflow::setCurrentDetails($this->chainFailedDetails(
                $chain['failedActionId'],
                $chain['failedError'],
                runningErrorChain: $onErrorActionIds !== [],
            ));

            if ($onErrorActionIds !== []) {
                $errorChain = yield from $this->runChain(
                    $activity,
                    $onErrorActionIds,
                    $codeMap,
                    $scopeMap,
                    $backoffByActionId,
                    $delayBeforeByActionId,
                    $context,
                    $scenarioRunId,
                    $actionNodeId,
                );
                $context = $errorChain['context'];
            }
        }

        if ($scenarioRunId !== null && $scenarioNodeId !== null) {
            $resumeActivity = Workflow::newActivityStub(
                ResumeScenarioRunActivityInterface::class,
                ActivityOptions::new()->withStartToCloseTimeout(30),
            );

            yield $resumeActivity->resume($scenarioRunId, $scenarioNodeId, $success, $context);
        }

        return $context;
    }

    /**
     * @param  ExecuteActionActivityInterface  $activity  прокси Workflow::newActivityStub(), не implements интерфейс в рантайме
     * @param  list<string>              $actionIds
     * @param  array<string, string>     $codeMap
     * @param  array<string, string>     $scopeMap
     * @param  array<string, list<int>>  $backoffByActionId
     * @param  array<string, int>        $delayBeforeByActionId
     * @param  array<string, mixed>      $context
     * @return \Generator<int, mixed, mixed, array{context: array<string, mixed>, failedActionId: string|null, failedError: string|null}>
     */
    private function runChain(
        object $activity,
        array $actionIds,
        array $codeMap,
        array $scopeMap,
        array $backoffByActionId,
        array $delayBeforeByActionId,
        array $context,
        ?string $scenarioRunId,
        ?string $actionNodeId = null,
    ) {
        foreach ($actionIds as $actionId) {
            $code = $codeMap[$actionId] ?? $actionId;

            try {
                $result = yield from $this->runActionWithBackoff(
                    $activity,
                    $actionId,
                    $code,
                    $context,
                    $scenarioRunId,
                    $delayBeforeByActionId[$actionId] ?? 0,
                    $backoffByActionId[$actionId] ?? [],
                    $actionNodeId,
                );
            } catch (ActivityFailure $exception) {
                $previous = $exception->getPrevious();
                $detail = $previous instanceof ApplicationFailure ? $previous->getDetails()->getValue(0, 'string') : null;
                $error = is_string($detail) ? $detail : $exception->getMessage();

                return ['context' => $context, 'failedActionId' => $actionId, 'failedError' => $error];
            }

            $scope = array_key_exists($code, $scopeMap) ? $scopeMap[$code] : $code;

            if ($scope === '') {
                $context = [...$context, ...$result['output']];
            } else {
                $existing = is_array($context[$scope] ?? null) ? $context[$scope] : [];
                $context[$scope] = [...$existing, ...$result['output']];
            }
        }

        return ['context' => $context, 'failedActionId' => null, 'failedError' => null];
    }

    private function chainStartedDetails(int $totalSteps): string
    {
        return "### Запуск цепочки экшенов\nШагов: {$totalSteps}";
    }

    private function chainFailedDetails(?string $failedActionId, ?string $failedError, bool $runningErrorChain): string
    {
        $lines = [
            "### Экшен `{$failedActionId}` провалился",
            "**Ошибка:** {$failedError}",
        ];

        if ($runningErrorChain) {
            $lines[] = 'Выполняется error-цепочка…';
        }

        return implode("\n", $lines);
    }
}
