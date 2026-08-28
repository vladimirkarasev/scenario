<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Runtime;

use Illuminate\Contracts\Events\Dispatcher;
use Module\Scenario\Enums\ScenarioContextKey;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\Events\ScenarioRunFailed;
use Module\Scenario\Models\ScenarioRun;

final readonly class ScenarioRunLoopGuard
{
    private const int MAX_STEPS = 100;

    private const int MAX_VISITS_PER_NODE = 10;

    public function __construct(private Dispatcher $events)
    {
    }

    public function allows(ScenarioRun $run, string $nodeId): bool
    {
        $context = $run->context ?? [];
        $rawPlayer = $context[ScenarioContextKey::Player->value] ?? null;
        $player = is_array($rawPlayer) ? $rawPlayer : [];
        $totalSteps = $this->intValue($player['total_steps'] ?? null) + 1;
        $rawVisited = $player['visited'] ?? null;
        $visited = is_array($rawVisited) ? $rawVisited : [];
        $nodeVisits = $this->intValue($visited[$nodeId] ?? null) + 1;
        $updatedContext = [
            ...$context,
            ScenarioContextKey::Player->value => [
                'total_steps' => $totalSteps,
                'visited' => [...$visited, $nodeId => $nodeVisits],
            ],
        ];

        if ($totalSteps > self::MAX_STEPS || $nodeVisits > self::MAX_VISITS_PER_NODE) {
            $run->forceFill([
                'status' => ScenarioRunStatus::Failed,
                'context' => $updatedContext,
            ])->save();
            $this->events->dispatch(new ScenarioRunFailed($run));

            return false;
        }

        $run->forceFill(['context' => $updatedContext])->save();

        return true;
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }
}
