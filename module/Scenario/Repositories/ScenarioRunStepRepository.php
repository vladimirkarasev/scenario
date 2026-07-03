<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunStep;
use Module\Scenario\Services\RunContextKeys;

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
            ->update(['cancelled_at' => now()]);
    }

    public function cancel(ScenarioRunStep $step): void
    {
        $step->fill(['cancelled_at' => now()]);
        $step->save();
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(ScenarioRun $run, array $attributes): ScenarioRunStep
    {
        // Снапшот состояния исполнения на момент шага: версия+ревизия для рендера
        // и стек вызовов — чтобы откат (jump) в связных сценариях восстанавливал
        // правильную версию и точку возврата.
        $attributes['scenario_version_id'] ??= $run->scenario_version_id;
        $attributes['scenario_version_revision_id'] ??= $run->scenario_version_revision_id;
        if (!array_key_exists('call_stack', $attributes)) {
            $context = is_array($run->context) ? $run->context : [];
            $stack = $context[RunContextKeys::CALL_STACK] ?? [];
            $attributes['call_stack'] = is_array($stack) ? $stack : [];
        }

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
