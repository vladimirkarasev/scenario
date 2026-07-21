<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use Illuminate\Contracts\Container\Container;
use Module\Actions\DTO\RunActionsData;
use Module\Actions\Models\Action;
use Module\Actions\Temporal\RunActionsParallelWorkflowInput;
use Module\Actions\Temporal\RunActionsWorkflowInput;
use Module\Actions\Temporal\RunActionsWorkflowStarterInterface;

final readonly class ActionOrchestratorService
{
    public function __construct(
        private Container $container,
        private RunActionsWorkflowStarterInterface $workflowStarter,
    ) {}

    /** @return array<string, mixed> */
    public function runFromData(RunActionsData $data): array
    {
        $missing = $this->validateRequiredInputs($data);

        if ($missing !== []) {
            return ['status' => 'error', 'errors' => $missing];
        }

        if ($data->schedule !== null) {
            return $this->schedule($data);
        }

        return match ($data->mode) {
            'parallel' => $this->runParallel($data),
            default => $this->runSequential($data),
        };
    }

    private function scheduleService(): ActionScheduleService
    {
        return $this->container->make(ActionScheduleService::class);
    }

    /**
     * @return array<string, list<string>> code => list of missing field keys
     */
    private function validateRequiredInputs(RunActionsData $data): array
    {
        $allActions = [
            ...$data->actions,
            ...$data->before,
            ...$data->after,
            ...$data->onError,
        ];

        if ($allActions === []) {
            return [];
        }

        $actions = Action::query()->whereIn('id', array_values($allActions))->get()->keyBy('id');
        $missing = [];

        foreach ($allActions as $code => $actionId) {
            $action = $actions->get($actionId);

            if ($action === null) {
                continue;
            }

            $fields = $action->input_fields ?? [];
            $provided = is_array($data->input[$code] ?? null) ? $data->input[$code] : [];

            foreach ($fields as $field) {
                $key = $field['key'] ?? null;

                if (! is_string($key) || $key === '' || ! ($field['required'] ?? false)) {
                    continue;
                }

                $value = $provided[$key] ?? null;

                if ($value === null || $value === '') {
                    $missing[$code][] = $key;
                }
            }
        }

        return $missing;
    }

    /** @return array<string, mixed> */
    private function runSequential(RunActionsData $data): array
    {
        $ordered = [
            ...$data->beforeIds(),
            ...$data->actionIds(),
            ...$data->afterIds(),
        ];

        if ($ordered === []) {
            return ['status' => 'queued', 'queued' => 0];
        }

        $scenarioRunId = $this->stringOrNull($data->input['scenario_run_id'] ?? null);
        $scenarioNodeId = $this->stringOrNull($data->input['scenario_node_id'] ?? null);

        $this->workflowStarter->startSequential(new RunActionsWorkflowInput(
            actionIds: $ordered,
            onErrorActionIds: $data->onErrorIds(),
            codeMap: $data->codesByActionId(),
            context: $data->input,
            scopeMap: $data->scopeMap,
            backoffByActionId: $this->backoffByActionId($data),
            delayBeforeByActionId: $this->delayBeforeByActionId($data),
            scenarioRunId: $scenarioRunId,
            scenarioNodeId: $scenarioNodeId,
        ));

        return ['status' => 'queued', 'queued' => count($ordered)];
    }

    /** @return array<string, mixed> */
    private function runParallel(RunActionsData $data): array
    {
        if ($data->actionIds() === []) {
            return ['status' => 'queued', 'queued' => 0];
        }

        $scenarioRunId = $this->stringOrNull($data->input['scenario_run_id'] ?? null);

        $this->workflowStarter->startParallel(new RunActionsParallelWorkflowInput(
            beforeIds: $data->beforeIds(),
            actionIds: $data->actionIds(),
            afterIds: $data->afterIds(),
            onErrorActionIds: $data->onErrorIds(),
            codeMap: $data->codesByActionId(),
            context: $data->input,
            backoffByActionId: $this->backoffByActionId($data),
            delayBeforeByActionId: $this->delayBeforeByActionId($data),
            scenarioRunId: $scenarioRunId,
        ));

        return ['status' => 'queued', 'queued' => count($data->beforeIds()) + count($data->actionIds())];
    }

    /** @return array<string, list<int>> */
    private function backoffByActionId(RunActionsData $data): array
    {
        $result = [];

        foreach ($data->codesByActionId() as $actionId => $code) {
            $result[$actionId] = $data->backoffMap[$code] ?? [];
        }

        return $result;
    }

    /** @return array<string, int> */
    private function delayBeforeByActionId(RunActionsData $data): array
    {
        $result = [];

        foreach ($data->codesByActionId() as $actionId => $code) {
            $result[$actionId] = $data->delayBeforeMap[$code] ?? 0;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function schedule(RunActionsData $data): array
    {
        $config = $data->schedule ?? [];
        $primaryId = $data->actionIds()[0] ?? null;

        if ($primaryId === null) {
            return ['status' => 'error', 'error' => 'schedule requires at least one action'];
        }

        $action = Action::query()->findOrFail($primaryId);

        $cron = is_string($config['cron'] ?? null) ? (string) $config['cron'] : null;
        $timezone = is_string($config['timezone'] ?? null) ? (string) $config['timezone'] : null;

        $schedule = $this->scheduleService()->upsert(
            action: $action,
            enabled: true,
            cron: $cron,
            timezone: $timezone,
            input: $data->input,
            options: [
                'mode' => $data->mode,
                'actions' => $data->actions,
                'before' => $data->before,
                'after' => $data->after,
                'on_error' => $data->onError,
            ],
            settings: [],
        );

        return [
            'status' => 'scheduled',
            'schedule_id' => $schedule->id,
            'next_run_at' => $schedule->next_run_at?->toIso8601String(),
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
