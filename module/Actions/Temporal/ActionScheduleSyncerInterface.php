<?php

declare(strict_types=1);

namespace Module\Actions\Temporal;

use Module\Actions\Models\ActionSchedule;

interface ActionScheduleSyncerInterface
{
    public function sync(ActionSchedule $schedule): void;

    public function delete(int $scheduleId): void;
}
