<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Actions\DTO\ActionResult;
use Module\Actions\DTO\RunActionsData;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionExecutor;
use Module\Actions\Services\ActionOrchestratorService;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\ScenarioGraphResolver;
use Module\Scenario\Services\VariableResolver;

final readonly class ActionNodeHandler implements NodeHandlerInterface
{
    use NodeHelpers;

    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private VariableResolver $variableResolver,
        private ActionOrchestratorService $orchestrator,
        private ActionExecutor $executor,
    ) {}

    public function isInteractive(array $node): bool
    {
        return ! $this->boolField($this->nodeData($node), 'skipInSurvey');
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        $this->dispatchActions($run, $node);

        return NodeAdvanceResult::next(
            $this->graphResolver->defaultNextNodeId($this->runVersion($run), $this->nodeId($node)),
        );
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        $this->dispatchActions($run, $node);

        return $this->graphResolver->defaultNextNodeId($this->runVersion($run), $this->nodeId($node));
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        return [
            'type' => $this->nodeType($node),
            'data' => $this->variableResolver->resolve($this->nodeData($node), $context),
        ];
    }

    /** @param array<string, mixed> $node */
    private function dispatchActions(ScenarioRun $run, array $node): void
    {
        $data = $this->nodeData($node);
        $context = is_array($run->context) ? $run->context : [];

        /** @var array<string, string> $actions */
        $actions = [];
        /** @var array<string, mixed> $input */
        $input = [];

        foreach ($this->arrayField($data, 'action_items') as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = $this->strField($item, 'code');
            $actionId = $this->strField($item, 'action_id');

            if ($code === '' || $actionId === '') {
                continue;
            }

            $actions[$code] = $actionId;
            $input[$code] = $this->resolveInput($item, 'input', $context);
        }

        $before = $this->collectHookItems($data, 'before_items', 'before_code', 'before_action_id', 'before_input', $context, $input);
        $onError = $this->collectHookItems($data, 'error_items', 'error_code', 'error_action_id', 'error_input', $context, $input);

        if ($actions === [] && $before === [] && $onError === []) {
            return;
        }

        $input['scenario_run_id'] = (string) $run->id;

        // Флаг «дождаться результата»: выполняем синхронно и сохраняем output в контекст рана
        // под code (например `{{ send_email.result }}`). Иначе — обычный асинхронный запуск.
        if ($this->boolField($data, 'wait_for_result')) {
            $this->runSync($run, $before, $actions, $onError, $input);

            return;
        }

        $mode = $this->strField($data, 'execution_mode', 'sequential');

        $this->orchestrator->runFromData(new RunActionsData(
            mode: $mode === 'parallel' ? 'parallel' : 'sequential',
            actions: $actions,
            before: $before,
            after: [],
            onError: $onError,
            input: $input,
            schedule: null,
            canManageActions: true,
        ));
    }

    /**
     * Синхронно выполняет before → основные → (onError при сбое) и записывает output
     * основных action в контекст рана под их code, чтобы он был доступен следующим нодам.
     *
     * @param array<string, string> $before  code => action_uuid
     * @param array<string, string> $main    code => action_uuid
     * @param array<string, string> $onError code => action_uuid
     * @param array<string, mixed>  $input   полная input-карта (включая scoped поля по code)
     */
    private function runSync(ScenarioRun $run, array $before, array $main, array $onError, array $input): void
    {
        foreach ($before as $actionId) {
            $this->executeOne($actionId, $input);
        }

        $failed = false;
        /** @var array<string, array<string, mixed>> $results */
        $results = [];

        foreach ($main as $code => $actionId) {
            $result = $this->executeOne($actionId, $input);

            if ($result === null) {
                continue;
            }

            $results[$code] = [
                'status' => $result->status->value,
                'result' => $result->output,
                'error' => $result->error,
            ];

            if ($result->status === ActionRunStatus::Failed) {
                $failed = true;
            }
        }

        if ($failed) {
            foreach ($onError as $actionId) {
                $this->executeOne($actionId, $input);
            }
        }

        if ($results === []) {
            return;
        }

        $context = is_array($run->context) ? $run->context : [];

        foreach ($results as $code => $payload) {
            $context[$code] = $payload;
        }

        $run->context = $context;
        $run->save();
    }

    /** @param array<string, mixed> $input */
    private function executeOne(string $actionId, array $input): ?ActionResult
    {
        $action = Action::query()->find($actionId);

        if ($action === null) {
            return null;
        }

        return $this->executor->execute($action, $input);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function collectHook(array $data, string $codeKey, string $idKey, string $inputKey, array $context, array &$input): array
    {
        $code = $this->strField($data, $codeKey);
        $actionId = $this->strField($data, $idKey);

        if ($code === '' || $actionId === '') {
            return [];
        }

        $input[$code] = $this->resolveInput($data, $inputKey, $context);

        return [$code => $actionId];
    }

    /**
     * Собирает hook-действия из массива items (новый формат). Если items пуст —
     * fallback на legacy single-hook поля для совместимости со старыми нодами.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function collectHookItems(
        array $data,
        string $itemsKey,
        string $legacyCodeKey,
        string $legacyIdKey,
        string $legacyInputKey,
        array $context,
        array &$input,
    ): array {
        $result = [];

        foreach ($this->arrayField($data, $itemsKey) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $code = $this->strField($item, 'code');
            $actionId = $this->strField($item, 'action_id');
            if ($code === '' || $actionId === '') {
                continue;
            }
            $input[$code] = $this->resolveInput($item, 'input', $context);
            $result[$code] = $actionId;
        }

        if ($result === []) {
            $result = $this->collectHook($data, $legacyCodeKey, $legacyIdKey, $legacyInputKey, $context, $input);
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed> $source
     * @param  array<string, mixed>    $context
     * @return array<string, mixed>
     */
    private function resolveInput(array $source, string $key, array $context): array
    {
        $raw = $source[$key] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        $resolved = $this->variableResolver->resolve($raw, $context);

        if (! is_array($resolved)) {
            return [];
        }

        $result = [];

        foreach ($resolved as $k => $value) {
            $result[(string) $k] = $value;
        }

        return $result;
    }
}
