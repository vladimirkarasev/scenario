<?php

declare(strict_types=1);

namespace Module\Schedule\Services;

use Illuminate\Contracts\Container\Container;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Client\Schedule\Policy\SchedulePolicies;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\ScheduleClientInterface;
use Throwable;

final readonly class TemporalScheduleSyncer implements TemporalScheduleSyncerInterface
{
    public function __construct(
        private Container $container,
    ) {}

    public function upsert(
        string $scheduleId,
        string $cron,
        string $timezone,
        string $workflowType,
        string $taskQueue,
        array $workflowInput,
    ): void {
        $definition = Schedule::new()
            ->withSpec(
                ScheduleSpec::new()
                    ->withAddedCronString($cron)
                    ->withTimezoneName($timezone),
            )
            ->withAction(
                StartWorkflowAction::new($workflowType)
                    ->withTaskQueue($taskQueue)
                    ->withInput($workflowInput),
            )
            ->withPolicies(SchedulePolicies::new()->withOverlapPolicy(ScheduleOverlapPolicy::Skip));

        $client = $this->client();
        $handle = $client->getHandle($scheduleId);

        $exists = true;

        try {
            $handle->describe();
        } catch (Throwable) {
            $exists = false;
        }

        if ($exists) {
            $handle->update($definition);
        } else {
            $client->createSchedule($definition, scheduleId: $scheduleId);
        }
    }

    public function delete(string $scheduleId): void
    {
        try {
            $this->client()->getHandle($scheduleId)->delete();
        } catch (Throwable) {
        }
    }

    private function client(): ScheduleClientInterface
    {
        return $this->container->make(ScheduleClientInterface::class);
    }
}
