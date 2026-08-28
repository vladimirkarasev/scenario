<?php

declare(strict_types=1);

namespace Module\Actions\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Module\Actions\Models\ActionSchedule;

final class ActionScheduleRepository
{
    /** @return Collection<int, ActionSchedule> */
    public function orderedWithAction(): Collection
    {
        return ActionSchedule::query()
            ->with('action')
            ->orderByDesc('enabled')
            ->orderBy('next_run_at')
            ->get();
    }

    /** @param  array<string, mixed>  $attributes */
    public function upsertForAction(string $actionId, array $attributes): ActionSchedule
    {
        return ActionSchedule::query()->updateOrCreate(
            ['action_id' => $actionId],
            $attributes,
        );
    }

    /** @param  array<string, mixed>  $attributes */
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
