<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'DirectorySync.')]
interface RunDirectorySyncScheduleActivityInterface
{
    /** @return mixed */
    #[ActivityMethod(name: 'RunDirectorySyncSchedule')]
    public function run(string $directoryId);
}
