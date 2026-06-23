<?php

declare(strict_types=1);

namespace Module\Directories\Repositories;

use Illuminate\Support\Collection;
use Module\Directories\Models\DirectoryImportSchedule;

final class DirectoryImportScheduleRepository
{
    /** @param  array<string, mixed>  $attributes */
    public function upsertForDirectory(string $directoryId, array $attributes): DirectoryImportSchedule
    {
        return DirectoryImportSchedule::query()->updateOrCreate(
            ['directory_id' => $directoryId],
            $attributes,
        );
    }

    /** @return Collection<int, DirectoryImportSchedule> */
    public function due(): Collection
    {
        return DirectoryImportSchedule::query()
            ->with('directory')
            ->where('enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->get();
    }

    public function lock(int $id): ?DirectoryImportSchedule
    {
        return DirectoryImportSchedule::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(DirectoryImportSchedule $schedule, array $attributes): DirectoryImportSchedule
    {
        $schedule->forceFill($attributes)->save();

        return $schedule;
    }
}
