<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Module\Directories\Models\Directory;
use Module\Directories\Services\DictionaryApiSyncService;
use Module\Directories\Services\DirectorySyncScheduleService;
use Throwable;

final readonly class RunDirectorySyncScheduleActivity implements RunDirectorySyncScheduleActivityInterface
{
    public function __construct(
        private DictionaryApiSyncService $apiSync,
        private DirectorySyncScheduleService $scheduleService,
    ) {}

    public function run(string $directoryId): void
    {
        $directory = Directory::query()->find($directoryId);

        if ($directory instanceof Directory && $directory->source_type === 'api') {
            try {
                $this->apiSync->queue($directory);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if (!$directory instanceof Directory) {
            return;
        }

        $schedule = $this->scheduleService->findForDirectory($directory);

        if ($schedule === null) {
            return;
        }

        $now = now();

        $schedule->forceFill([
            'last_run_at' => $now,
            'next_run_at' => $this->scheduleService->nextRunAt($schedule, $now),
        ])->save();
    }
}
