<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Action;

use Module\Actions\DTO\RunActionsData;
use Module\Actions\Services\ActionOrchestratorService;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Services\Nodes\NodeContextKeys;
use Module\Scenario\Services\Nodes\NodeHelpers;
use Module\Scenario\Services\VariableResolver;

/**
 * Механика выполнения action-ноды: сбор action-item'ов, запуск через оркестратор,
 * повтор с упавшей стадии и хранение состояния pipeline в контексте прогона.
 *
 * Состояние в context:
 *  - _action_runs[nodeId]   — общее состояние ноды: 'running' | 'failed' | 'done'
 *  - _action_stages[nodeId] — статусы отдельных стадий: code => 'running'|'success'|'failed'
 *
 * ActionNodeHandler решает «когда» (lifecycle), этот класс — «как».
 *
 * Термины:
 *  - orchCode — оркестрационный/input/event-ключ стадии (реальный code экшена, поле action_code);
 *  - scope    — куда сложить результат (поле code): пусто = глобальный scope (мерж в корень).
 */
final readonly class ActionNodePipeline
{
    use NodeHelpers;

    public function __construct(
        private VariableResolver $variableResolver,
        private ActionOrchestratorService $orchestrator,
    ) {
    }

    // ── Состояние ───────────────────────────────────────────────────────────────

    /**
     * Состояние ноды: null (не запускались) | Running | Failed | Done.
     *
     * @param  array<string, mixed>  $context
     */
    public function state(array $context, string $nodeId): ?ActionStatus
    {
        $runs = $context[NodeContextKeys::ACTION_RUNS] ?? null;
        $state = is_array($runs) ? ($runs[$nodeId] ?? null) : null;

        return is_string($state) ? ActionStatus::tryFrom($state) : null;
    }

    public function markState(ScenarioRun $run, string $nodeId, ActionStatus $state): void
    {
        $context = $this->runContext($run);
        $runs = is_array($context[NodeContextKeys::ACTION_RUNS] ?? null) ? $context[NodeContextKeys::ACTION_RUNS] : [];
        $runs[$nodeId] = $state->value;
        $context[NodeContextKeys::ACTION_RUNS] = $runs;

        $run->forceFill(['context' => $context])->save();
    }

    /**
     * Сохранённые статусы стадий (code => running|success|failed) — для восстановления
     * pipeline после перезагрузки. Пишутся инкрементально в ChainStepJob.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function stageResults(array $context, string $nodeId): array
    {
        $stages = $context[NodeContextKeys::ACTION_STAGES] ?? null;
        $nodeStages = is_array($stages) && is_array($stages[$nodeId] ?? null) ? $stages[$nodeId] : [];

        $result = [];
        foreach ($nodeStages as $code => $status) {
            if (is_string($code) && is_string($status)) {
                $result[$code] = $status;
            }
        }

        return $result;
    }

    // ── Стадии для render ─────────────────────────────────────────────────────────

    /**
     * Список стадий pipeline для отрисовки: orchCode (ключ для событий) + подпись.
     *
     * @param  array<string, mixed>  $node
     * @return list<array{code: string, name: string}>
     */
    public function stages(array $node): array
    {
        $data = $this->nodeData($node);
        $stages = [];

        foreach (['before_items', 'action_items'] as $itemsKey) {
            foreach ($this->arrayField($data, $itemsKey) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $code = $this->itemOrchCode($item);
                if ($code === '') {
                    continue;
                }

                $name = $this->strField($item, 'name');
                $scope = $this->strField($item, 'code');
                $stages[] = [
                    'code' => $code,
                    'name' => $name !== '' ? $name : ($scope !== '' ? $scope : $code),
                ];
            }
        }

        return $stages;
    }

    // ── Запуск ────────────────────────────────────────────────────────────────────

    /**
     * Собирает action-item'ы ноды и запускает их через оркестратор.
     * Возвращает true, если что-то было задиспатчено.
     *
     * Если передан $scenarioNodeId (путь wait_for_result), запуск всегда последовательный
     * и помечается scenario_node_id — это включает code-тегированный pipeline по WS
     * и авто-резюм прогона по завершении цепочки.
     *
     * @param  array<string, mixed>  $node
     */
    public function dispatch(ScenarioRun $run, array $node, ?string $scenarioNodeId = null): bool
    {
        $data = $this->nodeData($node);
        $context = $this->runContext($run);

        /** @var array<string, mixed> $input */
        $input = [];
        /** @var array<string, string> $scopeMap */
        $scopeMap = [];

        $actions = $this->collectScopedItems($this->arrayField($data, 'action_items'), $context, $input, $scopeMap);
        $before = $this->collectHookItems(
            $data,
            'before_items',
            'before_code',
            'before_action_id',
            'before_input',
            $context,
            $input,
            $scopeMap
        );
        $onError = $this->collectHookItems(
            $data,
            'error_items',
            'error_code',
            'error_action_id',
            'error_input',
            $context,
            $input,
            $scopeMap
        );

        if ($actions === [] && $before === [] && $onError === []) {
            return false;
        }

        $mode = $this->strField($data, 'execution_mode', 'sequential');

        if ($scenarioNodeId !== null) {
            $mode = 'sequential'; // pipeline всегда последовательный (стадии по порядку + накопление контекста)
        }

        $this->run($run, $mode, $actions, $before, $onError, $input, $scopeMap, $scenarioNodeId);

        return true;
    }

    /**
     * Повторяет выполнение pipeline начиная с упавшей стадии: успешные стадии остаются,
     * упавшая и последующие запускаются заново. Возвращает true, если повтор запущен.
     *
     * @param  array<string, mixed>  $node
     */
    public function retry(ScenarioRun $run, array $node): bool
    {
        $data = $this->nodeData($node);
        $nodeId = $this->nodeId($node);

        if (!$this->boolField($data, 'wait_for_result')) {
            return false;
        }

        $context = $this->runContext($run);

        /** @var array<string, mixed> $input */
        $input = [];
        /** @var array<string, string> $scopeMap */
        $scopeMap = [];

        $ordered = $this->collectScopedItems(
            [...$this->arrayField($data, 'before_items'), ...$this->arrayField($data, 'action_items')],
            $context,
            $input,
            $scopeMap,
        );
        $onError = $this->collectHookItems(
            $data,
            'error_items',
            'error_code',
            'error_action_id',
            'error_input',
            $context,
            $input,
            $scopeMap
        );

        $codes = array_keys($ordered);
        $failedCode = $this->firstFailedStage($context, $nodeId, $codes);

        if ($failedCode === null) {
            return false;
        }

        // Срез от упавшей стадии и далее.
        $sliceCodes = array_slice($codes, (int)array_search($failedCode, $codes, true));

        /** @var array<string, string> $retryActions */
        $retryActions = [];
        foreach ($sliceCodes as $code) {
            $retryActions[$code] = $ordered[$code];
        }

        $this->resetStages($run, $nodeId, $sliceCodes);
        $this->markState($run, $nodeId, ActionStatus::Running);

        $this->run($run, 'sequential', $retryActions, [], $onError, $input, $scopeMap, $nodeId);

        return true;
    }

    // ── Внутреннее ──────────────────────────────────────────────────────────────

    /**
     * Первая стадия со статусом 'failed' среди $codes (в порядке их следования).
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $codes
     */
    private function firstFailedStage(array $context, string $nodeId, array $codes): ?string
    {
        $stages = $this->stageResults($context, $nodeId);

        foreach ($codes as $code) {
            if (($stages[$code] ?? null) === ActionStatus::Failed->value) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Убирает статусы повторяемых стадий (успешные оставляет).
     *
     * @param  array<int, string>  $codes
     */
    private function resetStages(ScenarioRun $run, string $nodeId, array $codes): void
    {
        $context = $this->runContext($run);
        $stagesMap = is_array(
            $context[NodeContextKeys::ACTION_STAGES] ?? null
        ) ? $context[NodeContextKeys::ACTION_STAGES] : [];
        $nodeStages = is_array($stagesMap[$nodeId] ?? null) ? $stagesMap[$nodeId] : [];

        foreach ($codes as $code) {
            unset($nodeStages[$code]);
        }

        $stagesMap[$nodeId] = $nodeStages;
        $context[NodeContextKeys::ACTION_STAGES] = $stagesMap;

        $run->forceFill(['context' => $context])->save();
    }

    /**
     * @param  array<string, string>  $actions  orchCode => actionId
     * @param  array<string, string>  $before
     * @param  array<string, string>  $onError
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $scopeMap
     */
    private function run(
        ScenarioRun $run,
        string $mode,
        array $actions,
        array $before,
        array $onError,
        array $input,
        array $scopeMap,
        ?string $scenarioNodeId,
    ): void {
        $input['scenario_run_id'] = (string)$run->id;

        if ($scenarioNodeId !== null) {
            $input['scenario_node_id'] = $scenarioNodeId;
        }

        $this->orchestrator->runFromData(
            new RunActionsData(
                mode: $mode === 'parallel' ? 'parallel' : 'sequential',
                actions: $actions,
                before: $before,
                after: [],
                onError: $onError,
                input: $input,
                schedule: null,
                canManageActions: true,
                scopeMap: $scopeMap,
            )
        );
    }

    /** @return array<string, mixed> */
    private function runContext(ScenarioRun $run): array
    {
        return is_array($run->context) ? $run->context : [];
    }

    /**
     * Оркестрационный/input/event-ключ item: реальный code экшена (action_code).
     * Для старых нод (где action_code не сохранён) — fallback на code.
     *
     * @param  array<array-key, mixed>  $item
     */
    private function itemOrchCode(array $item): string
    {
        $code = $this->strField($item, 'action_code');

        return $code !== '' ? $code : $this->strField($item, 'code');
    }

    /**
     * Собирает items в карту orchCode => actionId, попутно наполняя input (scoped по orchCode)
     * и scopeMap (orchCode => result scope; пустой code = глобальный scope).
     *
     * @param  array<array-key, mixed>  $items
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $scopeMap
     * @return array<string, string>
     */
    private function collectScopedItems(array $items, array $context, array &$input, array &$scopeMap): array
    {
        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $actionId = $this->strField($item, 'action_id');
            $orchCode = $this->itemOrchCode($item);

            if ($actionId === '' || $orchCode === '') {
                continue;
            }

            $result[$orchCode] = $actionId;
            $input[$orchCode] = $this->resolveInput($item, 'input', $context);
            $scopeMap[$orchCode] = $this->strField($item, 'code');
        }

        return $result;
    }

    /**
     * Hook-действия (before/error) из items; при пустом списке — fallback на legacy single-hook поля.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $scopeMap
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
        array &$scopeMap,
    ): array {
        $result = $this->collectScopedItems($this->arrayField($data, $itemsKey), $context, $input, $scopeMap);

        if ($result === []) {
            $result = $this->collectLegacyHook($data, $legacyCodeKey, $legacyIdKey, $legacyInputKey, $context, $input);

            foreach ($result as $code => $_actionId) {
                $scopeMap[$code] = $code;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function collectLegacyHook(
        array $data,
        string $codeKey,
        string $idKey,
        string $inputKey,
        array $context,
        array &$input
    ): array {
        $code = $this->strField($data, $codeKey);
        $actionId = $this->strField($data, $idKey);

        if ($code === '' || $actionId === '') {
            return [];
        }

        $input[$code] = $this->resolveInput($data, $inputKey, $context);

        return [$code => $actionId];
    }

    /**
     * @param  array<array-key, mixed>  $source
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function resolveInput(array $source, string $key, array $context): array
    {
        $raw = $source[$key] ?? null;

        if (!is_array($raw)) {
            return [];
        }

        $resolved = $this->variableResolver->resolve($raw, $context);

        if (!is_array($resolved)) {
            return [];
        }

        $result = [];
        foreach ($resolved as $k => $value) {
            $result[(string)$k] = $value;
        }

        return $result;
    }
}
