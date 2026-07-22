<?php

declare(strict_types=1);

namespace Module\Scenario\Events;

use Module\Scenario\Models\ScenarioRun;

final readonly class ScenarioRunFailed
{
    public function __construct(
        public ScenarioRun $run,
    ) {}
}
