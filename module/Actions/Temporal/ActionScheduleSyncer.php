<?php

declare(strict_types=1);

namespace Module\Actions\Temporal;

use Module\Actions\Models\ActionSchedule;
use Module\Actions\Services\ActionScheduleService;
use Module\Actions\Temporal\Workflows\RunScheduledActionWorkflowInterface;
use Module\Schedule\Services\TemporalScheduleSyncerInterface;
use Module\Schedule\Support\TemporalTaskQueue;

final readonly class ActionScheduleSyncer implements ActionScheduleSyncerInterface
{
    public function __construct(
        private TemporalScheduleSyncerInterface $syncer,
        private TemporalTaskQueue $taskQueue,
    ) {}

    public function sync(ActionSchedule $schedule): void
    {
        $cron = $schedule->cron;

        if (!$schedule->enabled || $cron === null || $cron === '' || !ActionScheduleService::isValidCron($cron)) {
            $this->delete($schedule->id);

            return;
        }

        $timezoneRaw = config('app.timezone', 'UTC');
        $timezone = $schedule->timezone !== '' ? $schedule->timezone : (is_string($timezoneRaw) ? $timezoneRaw : 'UTC');

        $this->syncer->upsert(
            scheduleId: self::temporalId($schedule->id),
            cron: $cron,
            timezone: $timezone,
            workflowType: RunScheduledActionWorkflowInterface::WORKFLOW_TYPE,
            taskQueue: $this->taskQueue->value(),
            workflowInput: [$schedule->id],
        );
    }

    public function delete(int $scheduleId): void
    {
        $this->syncer->delete(self::temporalId($scheduleId));
    }

    /** @return non-empty-string */
    private static function temporalId(int $scheduleId): string
    {
        return "action-schedule-{$scheduleId}";
    }
}
