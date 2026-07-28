<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Support\Carbon;
use Module\Directories\Models\Directory;
use Module\Directories\Temporal\Workflows\RunDirectorySyncScheduleWorkflowInterface;
use Module\Schedule\Models\Schedule;
use Module\Schedule\Services\ScheduleDefinition;
use Module\Schedule\Services\ScheduleService;
use Module\Schedule\Support\TemporalTaskQueue;

final readonly class DirectorySyncScheduleService
{
    private const string SCOPE = 'directory-sync';

    public function __construct(
        private ScheduleService $schedules,
        private TemporalTaskQueue $taskQueue,
    ) {}

    public function upsert(Directory $directory, bool $enabled, ?string $cron, ?string $timezone): Schedule
    {
        $definition = ScheduleDefinition::new(self::SCOPE, $directory->id, RunDirectorySyncScheduleWorkflowInterface::WORKFLOW_TYPE)
            ->withEnabled($enabled)
            ->withCronString($cron)
            ->withTimezone($timezone)
            ->withTaskQueue($this->taskQueue->value())
            ->withWorkflowInput([$directory->id]);

        return $this->schedules->upsert($definition);
    }

    public function ensureDefault(Directory $directory): void
    {
        if ($this->findForDirectory($directory) !== null) {
            return;
        }

        $refreshInterval = $directory->api_config_json['refresh_interval'] ?? null;
        $seconds = is_int($refreshInterval) ? $refreshInterval : 3600;

        $this->upsert($directory, enabled: true, cron: $this->cronForInterval($seconds), timezone: null);
    }

    private function cronForInterval(int $seconds): string
    {
        $minutes = max(1, (int)round($seconds / 60));

        if ($minutes < 60) {
            return "*/{$minutes} * * * *";
        }

        $hours = max(1, (int)round($minutes / 60));

        if ($hours < 24) {
            return "0 */{$hours} * * *";
        }

        $days = max(1, (int)round($hours / 24));

        return "0 0 */{$days} * *";
    }

    public function disable(Directory $directory): void
    {
        $this->schedules->disable(self::SCOPE, $directory->id);
    }

    public function findForDirectory(Directory $directory): ?Schedule
    {
        return $this->schedules->findFor(self::SCOPE, $directory->id);
    }

    public static function isValidCron(string $cron): bool
    {
        return ScheduleService::isValidCron($cron);
    }

    public function nextRunAt(Schedule $schedule, ?Carbon $from = null): ?Carbon
    {
        return $this->schedules->nextRunAt($schedule, $from);
    }

    /** @return array<string, mixed>|null */
    public function payload(?Schedule $schedule): ?array
    {
        return $this->schedules->payload($schedule);
    }

    /** @return non-empty-string */
    public static function scope(): string
    {
        return self::SCOPE;
    }
}
