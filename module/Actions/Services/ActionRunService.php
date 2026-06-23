<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use Module\Actions\DTO\ActionRunIndexData;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionRun;
use Module\Actions\Repositories\ActionRunRepository;

final class ActionRunService
{
    public function __construct(
        private readonly ActionRunRepository $runs,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function items(ActionRunIndexData $filters): array
    {
        return $this->runs
            ->latestWithAction($filters)
            ->map(fn (ActionRun $run): array => $this->payload($run))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function payload(ActionRun $run): array
    {
        $action = $run->action instanceof Action ? $run->action : null;

        return [
            'id' => $run->id,
            'action_id' => $run->action_id,
            'action_name' => $action?->name,
            'action_key' => $action?->key,
            'status' => $run->status,
            'input' => $run->input,
            'output' => $run->output,
            'error' => $run->error,
            'reason' => $run->error,
            'attempts_count' => $run->attempts_count,
            'started_at' => $this->dateTime($run->started_at),
            'finished_at' => $this->dateTime($run->finished_at),
            'duration_ms' => $run->duration_ms,
            'created_at' => $this->dateTime($run->created_at),
        ];
    }

    private function dateTime(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : null;
    }
}
