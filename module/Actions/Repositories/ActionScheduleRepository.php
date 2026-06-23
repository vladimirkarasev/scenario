<?php

declare(strict_types=1);

namespace Module\Actions\Repositories;

use Illuminate\Support\Collection;
use Module\Actions\Models\ActionSchedule;

final class ActionScheduleRepository
{
    /** @param array<string, mixed> $attributes */
    public function upsertForAction(string $actionId, array $attributes): ActionSchedule
    {
        return ActionSchedule::query()->updateOrCreate(
            ['action_id' => $actionId],
            $attributes,
        );
    }

    /** @return Collection<int, ActionSchedule> */
    public function due(): Collection
    {
        return ActionSchedule::query()
            ->with('action')
            ->where('enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->get();
    }

    public function lock(int $id): ?ActionSchedule
    {
        return ActionSchedule::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    /** @param array<string, mixed> $attributes */
    public function update(ActionSchedule $schedule, array $attributes): ActionSchedule
    {
        $schedule->forceFill($attributes)->save();

        return $schedule;
    }

    public function delete(ActionSchedule $schedule): void
    {
        $schedule->delete();
    }
}
