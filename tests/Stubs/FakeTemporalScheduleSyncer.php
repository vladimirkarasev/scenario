<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Module\Schedule\Services\TemporalScheduleSyncerInterface;

final class FakeTemporalScheduleSyncer implements TemporalScheduleSyncerInterface
{
    /** @var list<array{scheduleId: string, cron: string, timezone: string, workflowType: string, taskQueue: string, workflowInput: list<mixed>}> */
    public array $upserts = [];

    /** @var list<string> */
    public array $deletedScheduleIds = [];

    public function upsert(
        string $scheduleId,
        string $cron,
        string $timezone,
        string $workflowType,
        string $taskQueue,
        array $workflowInput,
    ): void {
        $this->upserts[] = compact('scheduleId', 'cron', 'timezone', 'workflowType', 'taskQueue', 'workflowInput');
    }

    public function delete(string $scheduleId): void
    {
        $this->deletedScheduleIds[] = $scheduleId;
    }
}
