<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Bus;
use Module\Actions\DTO\RunActionsData;
use Module\Actions\Jobs\ChainStepJob;
use Module\Actions\Jobs\DispatchActionBatchJob;
use Module\Actions\Jobs\ExecuteActionJob;
use Module\Actions\Models\Action;

final readonly class ActionOrchestratorService
{
    public function __construct(
        private Container $container,
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
     * Проверяет что для каждого action в map есть все его required input_fields.
     *
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

        [$first, $remaining] = $this->shift($ordered);
        $scenarioRunId = $this->stringOrNull($data->input['scenario_run_id'] ?? null);
        $scenarioNodeId = $this->stringOrNull($data->input['scenario_node_id'] ?? null);

        ChainStepJob::dispatch(
            $first,
            $remaining,
            $data->onErrorIds(),
            $data->input,
            1,
            [60],
            $scenarioRunId,
            $data->codesByActionId(),
            $scenarioNodeId,
            $data->scopeMap,
        );

        return ['status' => 'queued', 'queued' => count($ordered)];
    }

    /** @return array<string, mixed> */
    private function runParallel(RunActionsData $data): array
    {
        if ($data->actionIds() === []) {
            return ['status' => 'queued', 'queued' => 0];
        }

        $scenarioRunId = $this->stringOrNull($data->input['scenario_run_id'] ?? null);

        $batchJob = new DispatchActionBatchJob(
            $data->actionIds(),
            $data->input,
            1,
            [60],
            $data->afterIds(),
            $data->onErrorIds(),
            $scenarioRunId,
        );

        if ($data->beforeIds() === []) {
            dispatch($batchJob);

            return ['status' => 'queued', 'queued' => count($data->actionIds())];
        }

        Bus::chain([
            ...$this->jobsForIds($data->beforeIds(), $data->input),
            $batchJob,
        ])->dispatch();

        return ['status' => 'queued', 'queued' => count($data->beforeIds()) + count($data->actionIds())];
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

    /**
     * @param  array<int, string>           $actionIds
     * @param  array<string, mixed>         $input
     * @return array<int, ExecuteActionJob>
     */
    private function jobsForIds(array $actionIds, array $input): array
    {
        $jobs = [];

        foreach ($actionIds as $id) {
            $jobs[] = new ExecuteActionJob($id, $input);
        }

        return $jobs;
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

    private function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
