<?php

declare(strict_types=1);

namespace Module\Actions\Temporal;

use Module\Actions\Models\ActionSchedule;
use Module\Actions\Services\ActionScheduleService;
use Module\Actions\Temporal\Workflows\RunScheduledActionWorkflowInterface;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Client\Schedule\Policy\SchedulePolicies;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\ScheduleClientInterface;

final readonly class ActionScheduleSyncer implements ActionScheduleSyncerInterface
{
    public function __construct(
        private ScheduleClientInterface $client,
    ) {}

    public function sync(ActionSchedule $schedule): void
    {
        $scheduleId = self::temporalId($schedule->id);
        $cron = $schedule->cron;

        if (!$schedule->enabled || $cron === null || $cron === '' || !ActionScheduleService::isValidCron($cron)) {
            $this->delete($schedule->id);

            return;
        }

        $timezoneRaw = config('app.timezone', 'UTC');
        $timezone = $schedule->timezone !== '' ? $schedule->timezone : (is_string($timezoneRaw) ? $timezoneRaw : 'UTC');

        $definition = Schedule::new()
            ->withSpec(
                ScheduleSpec::new()
                    ->withAddedCronString($cron)
                    ->withTimezoneName($timezone),
            )
            ->withAction(
                StartWorkflowAction::new(RunScheduledActionWorkflowInterface::WORKFLOW_TYPE)
                    ->withTaskQueue('default')
                    ->withInput([$schedule->id]),
            )
            ->withPolicies(SchedulePolicies::new()->withOverlapPolicy(ScheduleOverlapPolicy::Skip));

        $handle = $this->client->getHandle($scheduleId);

        $exists = true;

        try {
            $handle->describe();
        } catch (\Throwable) {
            $exists = false;
        }

        if ($exists) {
            $handle->update($definition);
        } else {
            $this->client->createSchedule($definition, scheduleId: $scheduleId);
        }
    }

    public function delete(int $scheduleId): void
    {
        try {
            $this->client->getHandle(self::temporalId($scheduleId))->delete();
        } catch (\Throwable) {
        }
    }

    /** @return non-empty-string */
    private static function temporalId(int $scheduleId): string
    {
        return "action-schedule-{$scheduleId}";
    }
}
