<?php

declare(strict_types=1);

namespace Module\Schedule\Services;

use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Module\Schedule\Models\Schedule;
use Module\Schedule\Repositories\ScheduleRepository;

final readonly class ScheduleService
{
    public function __construct(
        private ScheduleRepository $schedules,
        private TemporalScheduleSyncerInterface $syncer,
    ) {}

    public function upsert(ScheduleDefinition $definition): Schedule
    {
        return DB::transaction(function () use ($definition): Schedule {
            $schedule = $this->schedules->upsertFor($definition->scope, $definition->subjectId, [
                'enabled' => $definition->enabled,
                'cron' => $definition->cron?->getExpression(),
                'timezone' => $definition->timezone,
                'workflow_type' => $definition->workflowType,
                'task_queue' => $definition->taskQueue,
                'workflow_input' => $definition->workflowInput,
            ]);

            $this->schedules->update($schedule, [
                'next_run_at' => $definition->enabled ? $this->nextRunAt($schedule) : null,
            ]);

            $schedule = $schedule->fresh() ?? $schedule;

            $this->sync($schedule);

            return $schedule;
        });
    }

    public function disable(string $scope, string $subjectId): void
    {
        $schedule = $this->findFor($scope, $subjectId);

        if ($schedule instanceof Schedule) {
            $this->schedules->delete($schedule);
        }

        $this->syncer->delete(self::temporalId($scope, $subjectId));
    }

    public function findFor(string $scope, string $subjectId): ?Schedule
    {
        return $this->schedules->findFor($scope, $subjectId);
    }

    public static function isValidCron(string $cron): bool
    {
        return CronExpression::isValidExpression($cron);
    }

    public function nextRunAt(Schedule $schedule, ?Carbon $from = null): ?Carbon
    {
        if ($schedule->cron === null || $schedule->cron === '' || !CronExpression::isValidExpression($schedule->cron)) {
            return null;
        }

        $timezoneRaw = config('app.timezone', 'UTC');
        $scheduleTimezone = $schedule->timezone;
        $timezone = $scheduleTimezone !== '' ? $scheduleTimezone : (is_string($timezoneRaw) ? $timezoneRaw : 'UTC');
        $current = ($from ?? now())->copy()->timezone($timezone);

        $next = (new CronExpression($schedule->cron))->getNextRunDate($current->toDateTimeImmutable());

        return Carbon::instance($next)->utc();
    }

    /** @return array<string, mixed>|null */
    public function payload(?Schedule $schedule): ?array
    {
        if ($schedule === null) {
            return null;
        }

        return [
            'enabled' => $schedule->enabled,
            'cron' => $schedule->cron,
            'timezone' => $schedule->timezone,
            'last_run_at' => $schedule->last_run_at?->toIso8601String(),
            'next_run_at' => $schedule->next_run_at?->toIso8601String(),
        ];
    }

    private function sync(Schedule $schedule): void
    {
        $cron = $schedule->cron;
        $scheduleId = self::temporalId($schedule->scope, $schedule->subject_id);

        if (!$schedule->enabled || $cron === null || $cron === '' || !CronExpression::isValidExpression($cron)) {
            $this->syncer->delete($scheduleId);

            return;
        }

        $this->syncer->upsert(
            scheduleId: $scheduleId,
            cron: $cron,
            timezone: $schedule->timezone,
            workflowType: $schedule->workflow_type,
            taskQueue: $schedule->task_queue,
            workflowInput: is_array($schedule->workflow_input) ? $schedule->workflow_input : [],
        );
    }

    /** @return non-empty-string */
    private static function temporalId(string $scope, string $subjectId): string
    {
        return "{$scope}-{$subjectId}";
    }
}
