<?php

declare(strict_types=1);

namespace Module\Schedule\Services;

interface TemporalScheduleSyncerInterface
{
    /**
     * Creates or updates a native Temporal Schedule that starts the given workflow type
     * on the given cron expression. Always applies {@see \Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy::Skip}
     * so a slow-running previous execution is never overlapped by the next tick.
     *
     * @param  non-empty-string  $scheduleId
     * @param  non-empty-string  $cron
     * @param  list<mixed>  $workflowInput
     */
    public function upsert(
        string $scheduleId,
        string $cron,
        string $timezone,
        string $workflowType,
        string $taskQueue,
        array $workflowInput,
    ): void;

    /** @param  non-empty-string  $scheduleId */
    public function delete(string $scheduleId): void;
}
