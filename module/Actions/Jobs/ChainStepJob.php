<?php

declare(strict_types=1);

namespace Module\Actions\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Exceptions\ActionExecutionException;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionExecutor;
use Module\Scenario\Jobs\ResumeScenarioActionNodeJob;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Services\Nodes\Action\ActionStatus;
use Module\Scenario\Services\Nodes\NodeContextKeys;
use App\Events\CentrifugoMessagePublished;
use Throwable;

final class ChainStepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /**
     * @param array<int, string>    $remainingActionIds
     * @param array<int, string>    $failedActionIds
     * @param array<string, mixed>  $context
     * @param array<int, int>       $backoff
     * @param array<string, string> $codeMap            actionId => code override (если нет — используется $action->code)
     * @param array<string, string> $scopeMap           code => scope результата ('' = глобальный scope; нет ключа = под code)
     */
    public function __construct(
        private readonly string $actionId,
        private readonly array $remainingActionIds,
        private readonly array $failedActionIds,
        private readonly array $context,
        int $tries = 1,
        private readonly array $backoff = [60],
        private readonly ?string $scenarioRunId = null,
        private readonly array $codeMap = [],
        private readonly ?string $scenarioNodeId = null,
        private readonly array $scopeMap = [],
    ) {
        $this->tries = max(1, $tries);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return $this->backoff;
    }

    public function handle(ActionExecutor $executor): void
    {
        $action = Action::query()->findOrFail($this->actionId);

        $code = $this->codeMap[$this->actionId] ?? $action->code;

        if ($this->scenarioRunId !== null) {
            Event::dispatch(new CentrifugoMessagePublished("scenario-run:{$this->scenarioRunId}", [
                'type' => 'action_started',
                'action_id' => $this->actionId,
                'code' => $code,
            ]));

            $this->recordStageStatus($code, ActionStatus::Running);
        }

        $result = $executor->execute($action, $this->context, $this->attempts());

        if ($result->status === ActionRunStatus::Failed) {
            if ($this->attempts() >= $this->tries && $this->scenarioRunId !== null) {
                Event::dispatch(new CentrifugoMessagePublished("scenario-run:{$this->scenarioRunId}", [
                    'type' => 'action_failed',
                    'action_id' => $this->actionId,
                    'code' => $code,
                    'error' => $result->error,
                ]));

                $this->recordStageStatus($code, ActionStatus::Failed);
            }

            throw new ActionExecutionException($result->error ?? 'Action failed');
        }

        // Scope результата: пустой code → глобальный scope (мерж в корень, может перетираться);
        // иначе output складывается под scope-ключом (доступен как {{ scope.field }}).
        $context = $this->context;
        $output = is_array($result->output) ? $result->output : [];
        $scope = array_key_exists($code, $this->scopeMap) ? $this->scopeMap[$code] : $code;

        if ($scope === '') {
            $context = [...$context, ...$output];
        } else {
            $existing = is_array($context[$scope] ?? null) ? $context[$scope] : [];
            $context[$scope] = [...$existing, ...$output];
        }

        if ($this->scenarioRunId !== null) {
            Event::dispatch(new CentrifugoMessagePublished("scenario-run:{$this->scenarioRunId}", [
                'type' => 'action_completed',
                'action_id' => $this->actionId,
                'code' => $code,
                'status' => $result->status->value,
                'output' => $result->output,
            ]));

            $this->recordStageStatus($code, ActionStatus::Success);
        }

        if ($this->remainingActionIds === []) {
            if ($this->scenarioRunId !== null) {
                Event::dispatch(new CentrifugoMessagePublished("scenario-run:{$this->scenarioRunId}", [
                    'type' => 'chain_completed',
                    'context' => $context,
                ]));

                // Привязанная к прогону action-нода (wait_for_result): авто-продвигаем прогон.
                if ($this->scenarioNodeId !== null) {
                    ResumeScenarioActionNodeJob::dispatch(
                        $this->scenarioRunId,
                        $this->scenarioNodeId,
                        true,
                        $context,
                    );
                }
            }

            return;
        }

        [$nextActionId, $remaining] = $this->shift($this->remainingActionIds);

        self::dispatch(
            $nextActionId,
            $remaining,
            $this->failedActionIds,
            $context,
            $this->tries,
            $this->backoff,
            $this->scenarioRunId,
            $this->codeMap,
            $this->scenarioNodeId,
            $this->scopeMap,
        );
    }

    public function failed(Throwable $exception): void
    {
        // Привязанная к прогону action-нода: помечаем pipeline как failed и публикуем
        // обновлённое состояние (прогон остаётся на ноде до ручного «Продолжить»).
        if ($this->scenarioRunId !== null && $this->scenarioNodeId !== null) {
            ResumeScenarioActionNodeJob::dispatch(
                $this->scenarioRunId,
                $this->scenarioNodeId,
                false,
                $this->context,
            );
        }

        if ($this->failedActionIds === []) {
            return;
        }

        $failedContext = $this->context + [
            'failed_action_id' => $this->actionId,
            'failed_error' => $exception->getMessage(),
        ];

        [$firstFailed, $remainingFailed] = $this->shift($this->failedActionIds);

        self::dispatch(
            $firstFailed,
            $remainingFailed,
            [],
            $failedContext,
            $this->tries,
            $this->backoff,
            $this->scenarioRunId,
            $this->codeMap,
            $this->scenarioNodeId,
            $this->scopeMap,
        );
    }

    /**
     * @param  array<int, string>                      $ids
     * @return array{0: string, 1: array<int, string>}
     */
    private function shift(array $ids): array
    {
        $values = array_values($ids);
        $head = (string) array_shift($values);

        return [$head, $values];
    }

    /**
     * Инкрементально сохраняет статус стадии pipeline в контекст прогона
     * (context['_action_stages'][nodeId][code]), чтобы после перезагрузки страницы
     * pipeline восстанавливал реальное состояние, а не показывал «выполняется».
     */
    private function recordStageStatus(string $code, ActionStatus $status): void
    {
        if ($this->scenarioRunId === null || $this->scenarioNodeId === null) {
            return;
        }

        $run = ScenarioRun::query()->find($this->scenarioRunId);

        if ($run === null) {
            return;
        }

        $context = is_array($run->context) ? $run->context : [];
        $stages = is_array(
            $context[NodeContextKeys::ACTION_STAGES] ?? null
        ) ? $context[NodeContextKeys::ACTION_STAGES] : [];
        $nodeStages = is_array($stages[$this->scenarioNodeId] ?? null) ? $stages[$this->scenarioNodeId] : [];
        $nodeStages[$code] = $status->value;
        $stages[$this->scenarioNodeId] = $nodeStages;
        $context[NodeContextKeys::ACTION_STAGES] = $stages;

        $run->forceFill(['context' => $context])->save();
    }
}
