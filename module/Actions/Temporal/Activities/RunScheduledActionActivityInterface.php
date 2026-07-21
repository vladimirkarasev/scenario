<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'Actions.')]
interface RunScheduledActionActivityInterface
{
    /** @return mixed */
    #[ActivityMethod(name: 'RunScheduledAction')]
    public function run(int $scheduleId);
}
