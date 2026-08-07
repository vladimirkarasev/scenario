<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionScheduleService;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DirectorySyncScheduleService;

final class MigrateDirectorySyncSchedulesCommand extends Command
{
    protected $signature = 'directories:migrate-sync-schedules';

    protected $description = 'Migrate the legacy Action-based directory sync cron to the native DirectorySyncSchedule table';

    public function handle(ActionScheduleService $actionSchedules, DirectorySyncScheduleService $directorySchedules): int
    {
        $actions = Action::query()->where('code', 'like', 'directory_sync_%')->with('schedule')->get();

        $migrated = 0;

        foreach ($actions as $action) {
            $config = is_array($action->config) ? $action->config : [];
            $directoryId = $config['directory_id'] ?? null;
            $directory = is_string($directoryId) && $directoryId !== '' ? Directory::query()->find($directoryId) : null;

            $schedule = $action->schedule;

            if ($directory !== null && $schedule !== null) {
                $directorySchedules->upsert(
                    directory: $directory,
                    enabled: $schedule->enabled,
                    cron: $schedule->cron,
                    timezone: $schedule->timezone,
                );
                $migrated++;
            }

            if ($schedule !== null) {
                $actionSchedules->delete($schedule);
            }

            $action->delete();
        }

        $this->info("Migrated {$migrated} directory sync schedule(s), removed {$actions->count()} legacy action(s).");

        return self::SUCCESS;
    }
}
