<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Action;

use Module\Actions\DTO\RunActionsData;
use Module\Scenario\Models\ScenarioRun;

final class ActionPipelinePlan
{
    /** @var array<string, string> */
    private array $actions = [];

    /** @var array<string, string> */
    private array $before = [];

    /** @var array<string, string> */
    private array $onError = [];

    /** @var array<string, mixed> */
    private array $input = [];

    /** @var array<string, string> */
    private array $scopeMap = [];

    /** @var array<string, list<int>> */
    private array $backoffMap = [];

    /** @var array<string, int> */
    private array $delayBeforeMap = [];

    /**
     * @param  array<string, mixed>  $input
     * @param  list<int>  $backoff
     */
    public function add(
        ActionPipelineStage $stage,
        string $code,
        string $actionId,
        array $input,
        string $scope,
        array $backoff = [],
        int $delayBefore = 0,
    ): void {
        match ($stage) {
            ActionPipelineStage::Action => $this->actions[$code] = $actionId,
            ActionPipelineStage::Before => $this->before[$code] = $actionId,
            ActionPipelineStage::OnError => $this->onError[$code] = $actionId,
        };

        $this->input[$code] = $input;
        $this->scopeMap[$code] = $scope;
        $this->backoffMap[$code] = $backoff;
        $this->delayBeforeMap[$code] = $delayBefore;
    }

    public function isEmpty(): bool
    {
        return $this->actions === [] && $this->before === [] && $this->onError === [];
    }

    /** @return array<string, string> */
    public function actions(ActionPipelineStage $stage): array
    {
        return match ($stage) {
            ActionPipelineStage::Action => $this->actions,
            ActionPipelineStage::Before => $this->before,
            ActionPipelineStage::OnError => $this->onError,
        };
    }

    /**
     * @param  array<string, string>|null  $actions
     */
    public function toRunActionsData(
        ScenarioRun $run,
        string $mode,
        string $actionNodeId,
        ?string $scenarioNodeId = null,
        ?array $actions = null,
    ): RunActionsData {
        $input = [
            ...$this->input,
            'scenario_run_id' => (string) $run->id,
            'scenario_action_node_id' => $actionNodeId,
        ];

        if ($scenarioNodeId !== null) {
            $input['scenario_node_id'] = $scenarioNodeId;
        }

        return new RunActionsData(
            mode: $mode === 'parallel' ? 'parallel' : 'sequential',
            actions: $actions ?? $this->actions,
            before: $this->before,
            after: [],
            onError: $this->onError,
            input: $input,
            schedule: null,
            canManageActions: true,
            scopeMap: $this->scopeMap,
            backoffMap: $this->backoffMap,
            delayBeforeMap: $this->delayBeforeMap,
        );
    }
}
