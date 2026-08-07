<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Action;

use Module\Actions\DTO\RunActionsData;
use Module\Actions\Services\ActionOrchestratorService;
use Module\Scenario\Enums\ScenarioContextKey;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Services\Nodes\NodeHelpers;
use Module\Scenario\Services\VariableResolver;

final readonly class ActionNodePipeline
{
    use NodeHelpers;

    public function __construct(
        private VariableResolver $variableResolver,
        private ActionOrchestratorService $orchestrator,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function state(array $context, string $nodeId): ?ActionStatus
    {
        $runs = $context[ScenarioContextKey::ActionRuns->value] ?? null;
        $state = is_array($runs) ? ($runs[$nodeId] ?? null) : null;

        return is_string($state) ? ActionStatus::tryFrom($state) : null;
    }

    public function markState(ScenarioRun $run, string $nodeId, ActionStatus $state): void
    {
        $context = $this->runContext($run);
        $runs = is_array($context[ScenarioContextKey::ActionRuns->value] ?? null)
            ? $context[ScenarioContextKey::ActionRuns->value]
            : [];
        $runs[$nodeId] = $state->value;
        $context[ScenarioContextKey::ActionRuns->value] = $runs;

        $run->forceFill(['context' => $context])->save();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function stageResults(array $context, string $nodeId): array
    {
        $stages = $context[ScenarioContextKey::ActionStages->value] ?? null;
        $nodeStages = is_array($stages) && is_array($stages[$nodeId] ?? null) ? $stages[$nodeId] : [];

        $result = [];
        foreach ($nodeStages as $code => $status) {
            if (is_string($code) && is_string($status)) {
                $result[$code] = $status;
            }
        }

        return $result;
    }

    /**
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

    /**
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
        /** @var array<string, list<int>> $backoffMap */
        $backoffMap = [];
        /** @var array<string, int> $delayBeforeMap */
        $delayBeforeMap = [];

        $actions = $this->collectScopedItems(
            $this->arrayField($data, 'action_items'),
            $context,
            $input,
            $scopeMap,
            $backoffMap,
            $delayBeforeMap,
        );
        $before = $this->collectHookItems(
            $data,
            'before_items',
            'before_code',
            'before_action_id',
            'before_input',
            $context,
            $input,
            $scopeMap,
            $backoffMap,
            $delayBeforeMap,
        );
        $onError = $this->collectHookItems(
            $data,
            'error_items',
            'error_code',
            'error_action_id',
            'error_input',
            $context,
            $input,
            $scopeMap,
            $backoffMap,
            $delayBeforeMap,
        );

        if ($actions === [] && $before === [] && $onError === []) {
            return false;
        }

        $mode = $this->strField($data, 'execution_mode', 'sequential');

        if ($scenarioNodeId !== null) {
            $mode = 'sequential';
        }

        $this->run($run, $mode, $actions, $before, $onError, $input, $scopeMap, $backoffMap, $delayBeforeMap, $scenarioNodeId, $this->nodeId($node));

        return true;
    }

    /**
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
        /** @var array<string, list<int>> $backoffMap */
        $backoffMap = [];
        /** @var array<string, int> $delayBeforeMap */
        $delayBeforeMap = [];

        $ordered = $this->collectScopedItems(
            [...$this->arrayField($data, 'before_items'), ...$this->arrayField($data, 'action_items')],
            $context,
            $input,
            $scopeMap,
            $backoffMap,
            $delayBeforeMap,
        );
        $onError = $this->collectHookItems(
            $data,
            'error_items',
            'error_code',
            'error_action_id',
            'error_input',
            $context,
            $input,
            $scopeMap,
            $backoffMap,
            $delayBeforeMap,
        );

        $codes = array_keys($ordered);
        $failedCode = $this->firstFailedStage($context, $nodeId, $codes);

        if ($failedCode === null) {
            return false;
        }

        $sliceCodes = array_slice($codes, (int)array_search($failedCode, $codes, true));

        /** @var array<string, string> $retryActions */
        $retryActions = [];
        foreach ($sliceCodes as $code) {
            $retryActions[$code] = $ordered[$code];
        }

        $this->resetStages($run, $nodeId, $sliceCodes);
        $this->markState($run, $nodeId, ActionStatus::Running);

        $this->run($run, 'sequential', $retryActions, [], $onError, $input, $scopeMap, $backoffMap, $delayBeforeMap, $nodeId, $nodeId);

        return true;
    }

    /**
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
     * @param  array<int, string>  $codes
     */
    private function resetStages(ScenarioRun $run, string $nodeId, array $codes): void
    {
        $context = $this->runContext($run);
        $stagesMap = is_array(
            $context[ScenarioContextKey::ActionStages->value] ?? null
        ) ? $context[ScenarioContextKey::ActionStages->value] : [];
        $nodeStages = is_array($stagesMap[$nodeId] ?? null) ? $stagesMap[$nodeId] : [];

        foreach ($codes as $code) {
            unset($nodeStages[$code]);
        }

        $stagesMap[$nodeId] = $nodeStages;
        $context[ScenarioContextKey::ActionStages->value] = $stagesMap;

        $run->forceFill(['context' => $context])->save();
    }

    /**
     * @param  array<string, string>     $actions  orchCode => actionId
     * @param  array<string, string>     $before
     * @param  array<string, string>     $onError
     * @param  array<string, mixed>      $input
     * @param  array<string, string>     $scopeMap
     * @param  array<string, list<int>>  $backoffMap
     * @param  array<string, int>        $delayBeforeMap
     */
    private function run(
        ScenarioRun $run,
        string $mode,
        array $actions,
        array $before,
        array $onError,
        array $input,
        array $scopeMap,
        array $backoffMap,
        array $delayBeforeMap,
        ?string $scenarioNodeId,
        string $actionNodeId,
    ): void {
        $input['scenario_run_id'] = (string)$run->id;
        $input['scenario_action_node_id'] = $actionNodeId;

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
                backoffMap: $backoffMap,
                delayBeforeMap: $delayBeforeMap,
            )
        );
    }

    /** @return array<string, mixed> */
    private function runContext(ScenarioRun $run): array
    {
        return is_array($run->context) ? $run->context : [];
    }

    /**
     * @param  array<array-key, mixed>  $item
     */
    private function itemOrchCode(array $item): string
    {
        $code = $this->strField($item, 'action_code');

        return $code !== '' ? $code : $this->strField($item, 'code');
    }

    /**
     * @param  array<array-key, mixed>   $items
     * @param  array<string, mixed>      $context
     * @param  array<string, mixed>      $input
     * @param  array<string, string>     $scopeMap
     * @param  array<string, list<int>>  $backoffMap
     * @param  array<string, int>        $delayBeforeMap
     * @return array<string, string>
     */
    private function collectScopedItems(
        array $items,
        array $context,
        array &$input,
        array &$scopeMap,
        array &$backoffMap,
        array &$delayBeforeMap,
    ): array {
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
            $backoffMap[$orchCode] = $this->intListField($item, 'backoff');
            $delayBeforeMap[$orchCode] = $this->intField($item, 'delay_before');
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>      $data
     * @param  array<string, mixed>      $context
     * @param  array<string, mixed>      $input
     * @param  array<string, string>     $scopeMap
     * @param  array<string, list<int>>  $backoffMap
     * @param  array<string, int>        $delayBeforeMap
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
        array &$backoffMap,
        array &$delayBeforeMap,
    ): array {
        $result = $this->collectScopedItems(
            $this->arrayField($data, $itemsKey),
            $context,
            $input,
            $scopeMap,
            $backoffMap,
            $delayBeforeMap,
        );

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
