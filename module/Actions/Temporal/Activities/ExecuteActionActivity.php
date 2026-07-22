<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Activities;

use App\Events\CentrifugoMessagePublished;
use Illuminate\Support\Facades\Event;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Events\ScenarioActionStageFinished;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionExecutor;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Failure\ApplicationFailure;

final readonly class ExecuteActionActivity implements ExecuteActionActivityInterface
{
    public function __construct(
        private ActionExecutor $executor,
    ) {}

    public function execute(string $actionId, array $context, string $code, ?string $scenarioRunId, int $attemptNumber = 1, ?string $actionNodeId = null): array
    {
        $action = Action::query()->findOrFail($actionId);

        if ($scenarioRunId !== null) {
            Event::dispatch(new CentrifugoMessagePublished("scenario-run:{$scenarioRunId}", [
                'type' => 'action_started',
                'action_id' => $actionId,
                'code' => $code,
            ]));
        }

        $result = $this->executor->execute($action, $context, $attemptNumber);

        if ($result->status === ActionRunStatus::Failed) {
            if ($scenarioRunId !== null) {
                Event::dispatch(new CentrifugoMessagePublished("scenario-run:{$scenarioRunId}", [
                    'type' => 'action_failed',
                    'action_id' => $actionId,
                    'code' => $code,
                    'error' => $result->error,
                ]));

                if ($actionNodeId !== null) {
                    Event::dispatch(new ScenarioActionStageFinished(
                        $scenarioRunId,
                        $actionNodeId,
                        $actionId,
                        $action->name,
                        $code,
                        ActionRunStatus::Failed,
                        $context,
                        $result->output,
                        $result->error,
                    ));
                }
            }

            $error = $result->error ?? 'Action failed';

            throw new ApplicationFailure($error, 'ActionExecutionFailed', false, EncodedValues::fromValues([$error]));
        }

        if ($scenarioRunId !== null) {
            Event::dispatch(new CentrifugoMessagePublished("scenario-run:{$scenarioRunId}", [
                'type' => 'action_completed',
                'action_id' => $actionId,
                'code' => $code,
                'output' => $result->output,
            ]));

            if ($actionNodeId !== null) {
                Event::dispatch(new ScenarioActionStageFinished(
                    $scenarioRunId,
                    $actionNodeId,
                    $actionId,
                    $action->name,
                    $code,
                    $result->status,
                    $context,
                    $result->output,
                    $result->error,
                ));
            }
        }

        return [
            'status' => $result->status->value,
            'output' => $result->output ?? [],
            'error' => $result->error,
        ];
    }
}
