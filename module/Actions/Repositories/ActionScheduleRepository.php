<?php

declare(strict_types=1);

namespace Module\Actions\Repositories;

use Module\Actions\Models\ActionSchedule;

final class ActionScheduleRepository
{
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
