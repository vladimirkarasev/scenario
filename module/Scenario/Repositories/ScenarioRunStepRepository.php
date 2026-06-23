<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunStep;

final class ScenarioRunStepRepository
{
    public function openForNode(ScenarioRun $run, string $nodeId): ?ScenarioRunStep
    {
        return $run->steps()
            ->where('node_id', $nodeId)
            ->whereNull('exited_at')
            ->first();
    }

    public function latestOpenForCurrentNode(ScenarioRun $run): ?ScenarioRunStep
    {
        return $run->steps()
            ->where('node_id', $run->current_node_id)
            ->whereNull('exited_at')
            ->latest('id')
            ->first();
    }

    public function latestForNode(ScenarioRun $run, string $nodeId): ?ScenarioRunStep
    {
        return $run->steps()
            ->where('node_id', $nodeId)
            ->latest('id')
            ->first();
    }

    public function trimAfter(ScenarioRun $run, int $stepId): void
    {
        $run->steps()
            ->where('id', '>', $stepId)
            ->delete();
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(ScenarioRun $run, array $attributes): ScenarioRunStep
    {
        return $run->steps()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(ScenarioRunStep $step, array $attributes): ScenarioRunStep
    {
        $step->fill($attributes);
        $step->save();

        return $step;
    }
}
