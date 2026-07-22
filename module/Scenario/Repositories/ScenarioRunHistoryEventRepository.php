<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use Module\Scenario\Enums\ScenarioRunHistoryEventType;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunHistoryEvent;

final class ScenarioRunHistoryEventRepository
{
    /** @param  array<string, mixed>  $attributes */
    public function record(ScenarioRun $run, ScenarioRunHistoryEventType $type, array $attributes = []): ScenarioRunHistoryEvent
    {
        return ScenarioRunHistoryEvent::query()->create([
            'run_id' => $run->id,
            'step_id' => $attributes['step_id'] ?? null,
            'scenario_version_id' => $attributes['scenario_version_id'] ?? $run->scenario_version_id,
            'scenario_version_revision_id' => $attributes['scenario_version_revision_id'] ?? $run->scenario_version_revision_id,
            'node_id' => $attributes['node_id'] ?? null,
            'node_type' => $attributes['node_type'] ?? null,
            'type' => $type->value,
            'actor_id' => $run->operator_id,
            'payload' => $attributes['payload'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Последнее записанное значение поля узла в рамках прогона (для диффа при следующем визите).
     */
    public function lastFieldEvent(string $runId, string $nodeId, string $fieldId): ?ScenarioRunHistoryEvent
    {
        return ScenarioRunHistoryEvent::query()
            ->where('run_id', $runId)
            ->where('node_id', $nodeId)
            ->whereIn('type', [
                ScenarioRunHistoryEventType::FieldFilled->value,
                ScenarioRunHistoryEventType::FieldChanged->value,
            ])
            ->where('payload->field_id', $fieldId)
            ->latest('id')
            ->first();
    }
}
