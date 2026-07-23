<?php

declare(strict_types=1);

namespace Module\Schedule\Repositories;

use Module\Schedule\Models\Schedule;

final class ScheduleRepository
{
    /** @param  array<string, mixed>  $attributes */
    public function upsertFor(string $scope, string $subjectId, array $attributes): Schedule
    {
        return Schedule::query()->updateOrCreate(
            ['scope' => $scope, 'subject_id' => $subjectId],
            $attributes,
        );
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(Schedule $schedule, array $attributes): Schedule
    {
        $schedule->forceFill($attributes)->save();

        return $schedule;
    }

    public function delete(Schedule $schedule): void
    {
        $schedule->delete();
    }

    public function findFor(string $scope, string $subjectId): ?Schedule
    {
        return Schedule::query()->where('scope', $scope)->where('subject_id', $subjectId)->first();
    }
}
