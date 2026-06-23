<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use Illuminate\Support\Carbon;
use Module\Actions\DTO\ActionResult;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionRun;

final class ActionLogger
{
    /** @param  array<string, mixed>  $input */
    public function start(Action $action, array $input = [], int $attemptsCount = 1): ActionRun
    {
        return ActionRun::query()->create([
            'action_id' => $action->id,
            'status' => ActionRunStatus::Running->value,
            'input' => $input,
            'attempts_count' => max(1, $attemptsCount),
            'started_at' => now(),
        ]);
    }

    public function complete(ActionRun $run, ActionResult $result): ActionRun
    {
        $finishedAt = now();
        $startedAt = $this->carbon($run->started_at) ?? $finishedAt;

        $run->fill([
            'status' => $result->status->value,
            'output' => $result->output,
            'error' => $result->error,
            'finished_at' => $finishedAt,
            'duration_ms' => (int)round(max(0, $startedAt->diffInMilliseconds($finishedAt))),
        ])->save();

        return $run;
    }

    private function carbon(mixed $value): ?Carbon
    {
        return $value instanceof Carbon ? $value : null;
    }
}
