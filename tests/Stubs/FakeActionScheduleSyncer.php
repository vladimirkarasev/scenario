<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Module\Actions\Models\ActionSchedule;
use Module\Actions\Temporal\ActionScheduleSyncerInterface;

final class FakeActionScheduleSyncer implements ActionScheduleSyncerInterface
{
    public array $syncedSchedules = [];

    public array $deletedScheduleIds = [];

    public function sync(ActionSchedule $schedule): void
    {
        $this->syncedSchedules[] = $schedule;
    }

    public function delete(int $scheduleId): void
    {
        $this->deletedScheduleIds[] = $scheduleId;
    }
}
