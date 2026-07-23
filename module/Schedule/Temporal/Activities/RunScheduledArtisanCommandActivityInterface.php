<?php

declare(strict_types=1);

namespace Module\Schedule\Temporal\Activities;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'System.')]
interface RunScheduledArtisanCommandActivityInterface
{
    /** @return mixed */
    #[ActivityMethod(name: 'RunScheduledArtisanCommand')]
    public function run(string $command);
}
