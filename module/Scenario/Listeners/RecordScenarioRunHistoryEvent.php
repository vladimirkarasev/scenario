<?php

declare(strict_types=1);

namespace Module\Scenario\Listeners;

use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Events\ScenarioActionStageFinished;
use Module\Scenario\Enums\ScenarioNodeType;
use Module\Scenario\Enums\ScenarioRunHistoryEventType;
use Module\Scenario\Events\ScenarioConditionEvaluated;
use Module\Scenario\Events\ScenarioLinkFollowed;
use Module\Scenario\Events\ScenarioNodeEntered;
use Module\Scenario\Events\ScenarioNodeExited;
use Module\Scenario\Events\ScenarioRunCompleted;
use Module\Scenario\Events\ScenarioRunFailed;
use Module\Scenario\Events\ScenarioRunRewound;
use Module\Scenario\Events\ScenarioRunStarted;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Repositories\ScenarioRunHistoryEventRepository;

final readonly class RecordScenarioRunHistoryEvent
{
    private const array TRANSITION_NODE_TYPES = [
        ScenarioNodeType::Block->value,
        ScenarioNodeType::Condition->value,
    ];

    public function __construct(
        private ScenarioRunHistoryEventRepository $history,
    ) {}

    public function handleRunStarted(ScenarioRunStarted $event): void
    {
        $this->history->record($event->run, ScenarioRunHistoryEventType::RunStarted, [
            'node_id' => $event->run->current_node_id,
        ]);
    }

    public function handleRunCompleted(ScenarioRunCompleted $event): void
    {
        $this->history->record($event->run, ScenarioRunHistoryEventType::RunCompleted, [
            'node_id' => $event->run->current_node_id,
        ]);
    }

    public function handleRunFailed(ScenarioRunFailed $event): void
    {
        $this->history->record($event->run, ScenarioRunHistoryEventType::RunFailed, [
            'node_id' => $event->run->current_node_id,
        ]);
    }

    public function handleRunRewound(ScenarioRunRewound $event): void
    {
        $this->history->record($event->run, ScenarioRunHistoryEventType::Cancelled, [
            'node_id' => $this->stringField($event->node, 'id'),
            'node_type' => $this->stringField($event->node, 'type'),
        ]);
    }

    public function handleNodeEntered(ScenarioNodeEntered $event): void
    {
        $nodeType = $this->stringField($event->node, 'type');

        if (!in_array($nodeType, self::TRANSITION_NODE_TYPES, true)) {
            return;
        }

        $this->history->record($event->run, ScenarioRunHistoryEventType::Transition, [
            'node_id' => $event->step->node_id,
            'node_type' => $nodeType,
            'step_id' => $event->step->id,
            'occurred_at' => $event->step->entered_at,
        ]);
    }

    public function handleNodeExited(ScenarioNodeExited $event): void
    {
        $nodeType = $this->stringField($event->node, 'type');

        if ($nodeType !== ScenarioNodeType::Block->value || $event->input === []) {
            return;
        }

        $nodeId = $event->step->node_id;
        $labels = $this->fieldLabels($event->node);

        foreach ($event->input as $fieldId => $newValue) {
            $fieldId = (string) $fieldId;
            $last = $this->history->lastFieldEvent($event->run->id, $nodeId, $fieldId);
            $hadPrior = $last !== null;
            $oldValue = $hadPrior ? ($last->payload['new_value'] ?? null) : null;

            if ($hadPrior && $oldValue === $newValue) {
                continue;
            }

            $type = (!$hadPrior || $oldValue === null)
                ? ScenarioRunHistoryEventType::FieldFilled
                : ScenarioRunHistoryEventType::FieldChanged;

            $this->history->record($event->run, $type, [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
                'step_id' => $event->step->id,
                'occurred_at' => $event->step->exited_at,
                'payload' => [
                    'field_id' => $fieldId,
                    'field_label' => $labels[$fieldId] ?? $fieldId,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ],
            ]);
        }
    }

    public function handleConditionEvaluated(ScenarioConditionEvaluated $event): void
    {
        $this->history->record($event->run, ScenarioRunHistoryEventType::ConditionEvaluated, [
            'node_id' => $this->stringField($event->node, 'id'),
            'node_type' => ScenarioNodeType::Condition->value,
            'payload' => [
                'mode' => $event->mode,
                'label' => $event->selectedLabel,
                'target_node_id' => $event->targetNodeId,
            ],
        ]);
    }

    public function handleLinkFollowed(ScenarioLinkFollowed $event): void
    {
        $this->history->record($event->run, ScenarioRunHistoryEventType::ScenarioLinkFollowed, [
            'node_id' => $event->step->node_id,
            'node_type' => ScenarioNodeType::ScenarioLink->value,
            'step_id' => $event->step->id,
            'occurred_at' => $event->step->exited_at,
            'payload' => [
                'target_scenario_id' => $event->targetScenarioId,
                'target_scenario_name' => $event->targetScenarioName,
                'target_version_id' => $event->targetVersionId,
                'start_node_id' => $event->startNodeId,
            ],
        ]);
    }

    public function handleActionStageFinished(ScenarioActionStageFinished $event): void
    {
        $run = ScenarioRun::query()->find($event->scenarioRunId);

        if ($run === null) {
            return;
        }

        $type = $event->status === ActionRunStatus::Failed
            ? ScenarioRunHistoryEventType::ActionFailed
            : ScenarioRunHistoryEventType::ActionCompleted;

        $this->history->record($run, $type, [
            'node_id' => $event->actionNodeId,
            'node_type' => ScenarioNodeType::Action->value,
            'payload' => [
                'action_id' => $event->actionId,
                'action_name' => $event->actionName,
                'code' => $event->code,
                'input' => $event->input,
                'output' => $event->output,
                'error' => $event->error,
            ],
        ]);
    }

    /** @param  array<string, mixed>  $node */
    private function stringField(array $node, string $key): ?string
    {
        $value = $node[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, string>
     */
    private function fieldLabels(array $node): array
    {
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];

        $labels = [];

        foreach ($fields as $i => $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = is_string($field['name'] ?? null) ? $field['name'] : 'field_'.($i + 1);
            $labels[$name] = is_string($field['label'] ?? null) ? $field['label'] : $name;
        }

        return $labels;
    }
}
