<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Action;

use Module\Actions\Services\ActionOrchestratorService;
use Module\Scenario\Enums\ScenarioContextKey;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Services\Nodes\NodeDataReader;
use Module\Scenario\Services\Variables\VariableResolver;

final readonly class ActionNodePipeline
{
    public function __construct(
        private VariableResolver $variableResolver,
        private ActionOrchestratorService $orchestrator,
        private NodeDataReader $nodeData,
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
        $data = $this->nodeData->data($node);
        $stages = [];

        foreach (['before_items', 'action_items'] as $itemsKey) {
            foreach ($this->nodeData->array($data, $itemsKey) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $code = $this->itemOrchCode($item);
                if ($code === '') {
                    continue;
                }

                $name = $this->nodeData->string($item, 'name');
                $scope = $this->nodeData->string($item, 'code');
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
        $data = $this->nodeData->data($node);
        $context = $this->runContext($run);

        $plan = new ActionPipelinePlan();
        $this->collectScopedItems(
            $this->nodeData->array($data, 'action_items'),
            $context,
            $plan,
            ActionPipelineStage::Action,
        );
        $this->collectHookItems(
            $data,
            'before_items',
            'before_code',
            'before_action_id',
            'before_input',
            $context,
            $plan,
            ActionPipelineStage::Before,
        );
        $this->collectHookItems(
            $data,
            'error_items',
            'error_code',
            'error_action_id',
            'error_input',
            $context,
            $plan,
            ActionPipelineStage::OnError,
        );

        if ($plan->isEmpty()) {
            return false;
        }

        $mode = $this->nodeData->string($data, 'execution_mode', 'sequential');

        if ($scenarioNodeId !== null) {
            $mode = 'sequential';
        }

        $this->run($run, $mode, $plan, $this->nodeData->id($node), $scenarioNodeId);

        return true;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function retry(ScenarioRun $run, array $node): bool
    {
        $data = $this->nodeData->data($node);
        $nodeId = $this->nodeData->id($node);

        if (!$this->nodeData->boolean($data, 'wait_for_result')) {
            return false;
        }

        $context = $this->runContext($run);

        $plan = new ActionPipelinePlan();
        $this->collectScopedItems(
            [...$this->nodeData->array($data, 'before_items'), ...$this->nodeData->array($data, 'action_items')],
            $context,
            $plan,
            ActionPipelineStage::Action,
        );
        $this->collectHookItems(
            $data,
            'error_items',
            'error_code',
            'error_action_id',
            'error_input',
            $context,
            $plan,
            ActionPipelineStage::OnError,
        );

        $ordered = $plan->actions(ActionPipelineStage::Action);
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

        $this->run($run, 'sequential', $plan, $nodeId, $nodeId, $retryActions);

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
     * @param  array<string, string>|null  $actions
     */
    private function run(
        ScenarioRun $run,
        string $mode,
        ActionPipelinePlan $plan,
        string $actionNodeId,
        ?string $scenarioNodeId = null,
        ?array $actions = null,
    ): void {
        $this->orchestrator->runFromData(
            $plan->toRunActionsData($run, $mode, $actionNodeId, $scenarioNodeId, $actions),
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
        $code = $this->nodeData->string($item, 'action_code');

        return $code !== '' ? $code : $this->nodeData->string($item, 'code');
    }

    /**
     * @param  array<array-key, mixed>   $items
     * @param  array<string, mixed>      $context
     */
    private function collectScopedItems(
        array $items,
        array $context,
        ActionPipelinePlan $plan,
        ActionPipelineStage $stage,
    ): void {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $actionId = $this->nodeData->string($item, 'action_id');
            $orchCode = $this->itemOrchCode($item);

            if ($actionId === '' || $orchCode === '') {
                continue;
            }

            $plan->add(
                $stage,
                $orchCode,
                $actionId,
                $this->resolveInput($item, 'input', $context),
                $this->nodeData->string($item, 'code'),
                $this->nodeData->integerList($item, 'backoff'),
                $this->nodeData->integer($item, 'delay_before'),
            );
        }
    }

    /**
     * @param  array<string, mixed>      $data
     * @param  array<string, mixed>      $context
     */
    private function collectHookItems(
        array $data,
        string $itemsKey,
        string $legacyCodeKey,
        string $legacyIdKey,
        string $legacyInputKey,
        array $context,
        ActionPipelinePlan $plan,
        ActionPipelineStage $stage,
    ): void {
        $this->collectScopedItems(
            $this->nodeData->array($data, $itemsKey),
            $context,
            $plan,
            $stage,
        );

        if ($plan->actions($stage) === []) {
            $this->collectLegacyHook($data, $legacyCodeKey, $legacyIdKey, $legacyInputKey, $context, $plan, $stage);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     */
    private function collectLegacyHook(
        array $data,
        string $codeKey,
        string $idKey,
        string $inputKey,
        array $context,
        ActionPipelinePlan $plan,
        ActionPipelineStage $stage,
    ): void {
        $code = $this->nodeData->string($data, $codeKey);
        $actionId = $this->nodeData->string($data, $idKey);

        if ($code === '' || $actionId === '') {
            return;
        }

        $plan->add(
            $stage,
            $code,
            $actionId,
            $this->resolveInput($data, $inputKey, $context),
            $code,
        );
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
